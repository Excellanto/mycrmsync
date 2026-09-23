<?php

namespace App\Services\Contacts;

/**
 * Ranks contact display names against a free-text name query.
 *
 * A two- or three-word query is a bag of words, so "Doe Jane" can match "Jane Doe".
 * `score` is a percentage from 0 to 100. 100 means the normalized name equals the query.
 */
final class ContactNameScorer
{
    private const COVERAGE_WEIGHT = 70.0;

    private const SIMILARITY_WEIGHT = 25.0;

    private const PREFIX_SCORE = 5.0;

    private const SIMILARITY_FLOOR = 0.45;

    private const CONFIDENT_GAP = 10;

    /**
     * Same expression as the contacts_name_trgm_idx index.
     * contacts_name_norm() is immutable so Postgres can index it.
     */
    public const NORMALIZED_NAME_SQL = 'contacts_name_norm(first_name, last_name)';

    /**
     * @param  list<array{id: string, first_name: string, last_name: string}>  $candidates
     * @return array{matches: list<array{id: string, score: int}>, confident: bool}
     */
    public function rank(string $query, array $candidates, int $limit): array
    {
        $limit = max(1, $limit);
        $queryNorm = self::normalize($query);
        $queryTokens = self::tokens($queryNorm);

        if ($queryTokens === []) {
            return ['matches' => [], 'confident' => false];
        }

        $ranked = [];

        foreach ($candidates as $candidate) {
            $nameNorm = self::normalize(trim($candidate['first_name'].' '.$candidate['last_name']));
            $nameTokens = self::tokens($nameNorm, minLength: 1);
            $scored = $this->score($queryNorm, $queryTokens, $nameNorm, $nameTokens);

            if ($scored === null) {
                continue;
            }

            $ranked[] = [
                'id' => $candidate['id'],
                'score' => $scored['score'],
                'exact' => $scored['exact'],
                'full_coverage' => $scored['full_coverage'],
                'extra' => $scored['extra'],
            ];
        }

        usort($ranked, function (array $left, array $right): int {
            $byScore = $right['score'] <=> $left['score'];

            if ($byScore !== 0) {
                return $byScore;
            }

            $byExtra = $left['extra'] <=> $right['extra'];

            return $byExtra !== 0 ? $byExtra : strcmp($left['id'], $right['id']);
        });

        $top = $ranked[0] ?? null;
        $second = $ranked[1] ?? null;
        $confident = false;

        if ($top !== null && $top['exact']) {
            $confident = $second === null || $second['score'] < $top['score'];
        } elseif ($top !== null && $top['full_coverage']) {
            $gap = $second === null ? self::CONFIDENT_GAP : ($top['score'] - $second['score']);
            $confident = $gap >= self::CONFIDENT_GAP;
        }

        $matches = [];

        foreach (array_slice($ranked, 0, $limit) as $row) {
            $matches[] = [
                'id' => $row['id'],
                'score' => $row['score'],
            ];
        }

        return [
            'matches' => $matches,
            'confident' => $confident,
        ];
    }

    /**
     * @param  list<string>  $queryTokens
     * @param  list<string>  $nameTokens
     * @return array{score: int, exact: bool, full_coverage: bool, extra: int}|null
     */
    private function score(string $queryNorm, array $queryTokens, string $nameNorm, array $nameTokens): ?array
    {
        if ($nameNorm === '') {
            return null;
        }

        $hits = self::tokenHits($queryTokens, $nameTokens);
        $similarity = self::wordSimilarity($queryNorm, $nameNorm);

        if ($hits === 0 && $similarity < self::SIMILARITY_FLOOR) {
            return null;
        }

        $exact = $nameNorm === $queryNorm;
        $coverage = $hits / count($queryTokens);
        $prefix = str_starts_with($nameNorm, $queryNorm) ? self::PREFIX_SCORE : 0.0;
        $score = $exact
            ? 100
            : min(99, (int) round(
                ($coverage * self::COVERAGE_WEIGHT)
                + ($similarity * self::SIMILARITY_WEIGHT)
                + $prefix
            ));

        return [
            'score' => $score,
            'exact' => $exact,
            'full_coverage' => $hits === count($queryTokens),
            'extra' => max(0, count($nameTokens) - $hits),
        ];
    }

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $normalized, int $minLength = 2): array
    {
        if ($normalized === '') {
            return [];
        }

        $tokens = [];

        foreach (explode(' ', $normalized) as $token) {
            if (mb_strlen($token) >= $minLength) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    /**
     * @param  list<string>  $queryTokens
     * @param  list<string>  $nameTokens
     */
    private static function tokenHits(array $queryTokens, array $nameTokens): int
    {
        $used = [];
        $hits = 0;

        foreach ($queryTokens as $queryToken) {
            $exactIndex = null;
            $prefixIndex = null;

            foreach ($nameTokens as $index => $nameToken) {
                if (isset($used[$index])) {
                    continue;
                }

                if ($nameToken === $queryToken) {
                    $exactIndex = $index;
                    break;
                }

                if ($prefixIndex === null && mb_strlen($queryToken) >= 3 && str_starts_with($nameToken, $queryToken)) {
                    $prefixIndex = $index;
                }
            }

            $chosen = $exactIndex ?? $prefixIndex;

            if ($chosen === null) {
                continue;
            }

            $used[$chosen] = true;
            $hits++;
        }

        return $hits;
    }

    /**
     * Best trigram similarity between the query and any contiguous slice of the name.
     */
    public static function wordSimilarity(string $query, string $text): float
    {
        if ($query === '' || $text === '') {
            return 0.0;
        }

        if ($query === $text) {
            return 1.0;
        }

        $length = mb_strlen($text);
        $best = 0.0;

        for ($start = 0; $start < $length; $start++) {
            for ($size = 1; $size <= $length - $start; $size++) {
                $best = max($best, self::similarity($query, mb_substr($text, $start, $size)));

                if ($best >= 1.0) {
                    return 1.0;
                }
            }
        }

        return $best;
    }

    public static function similarity(string $left, string $right): float
    {
        if ($left === $right) {
            return 1.0;
        }

        $leftTrigrams = self::trigrams($left);
        $rightTrigrams = self::trigrams($right);

        if ($leftTrigrams === [] || $rightTrigrams === []) {
            return 0.0;
        }

        $intersection = 0;
        $union = 0;

        foreach (array_unique([...array_keys($leftTrigrams), ...array_keys($rightTrigrams)]) as $trigram) {
            $leftCount = $leftTrigrams[$trigram] ?? 0;
            $rightCount = $rightTrigrams[$trigram] ?? 0;
            $intersection += min($leftCount, $rightCount);
            $union += max($leftCount, $rightCount);
        }

        return $union === 0 ? 0.0 : $intersection / $union;
    }

    /**
     * @return array<string, int>
     */
    private static function trigrams(string $value): array
    {
        $padded = '  '.$value.' ';
        $chars = mb_str_split($padded);
        $count = count($chars);
        $trigrams = [];

        for ($index = 0; $index <= $count - 3; $index++) {
            $trigram = $chars[$index].$chars[$index + 1].$chars[$index + 2];
            $trigrams[$trigram] = ($trigrams[$trigram] ?? 0) + 1;
        }

        return $trigrams;
    }
}
