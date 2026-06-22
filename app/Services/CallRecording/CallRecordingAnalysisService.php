<?php

namespace App\Services\CallRecording;

use App\Services\Integrations\OpenAiConfigService;
use OpenAI;
use RuntimeException;

final class CallRecordingAnalysisService
{
    /**
     * @return array{summary: string, sentiment: array<string, mixed>}
     */
    public function analyze(int $tenantId, string $transcription): array
    {
        $transcription = trim($transcription);
        if ($transcription === '') {
            return [
                'summary' => '',
                'sentiment' => $this->emptySentiment(),
            ];
        }

        $config = OpenAiConfigService::forTenant($tenantId);
        $apiKey = $config->apiKey();

        if ($apiKey === null) {
            throw new RuntimeException('OpenAI is not configured for call analysis.', 422);
        }

        $model = $config->gptModel();
        $client = OpenAI::client($apiKey);

        $response = $client->chat()->create([
            'model' => $model,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You analyze phone call transcripts for sales and support teams. '
                        .'Return JSON with exactly these keys: '
                        .'"summary" (string, concise call summary with key topics, outcome, and next steps), '
                        .'"sentiment" (object with keys: '
                        .'"overall" (one of: positive, neutral, negative), '
                        .'"score" (number from -1 to 1), '
                        .'"confidence" (number from 0 to 1), '
                        .'"customer" (object with "label" string and "score" number from -1 to 1), '
                        .'"agent" (object with "label" string and "score" number from -1 to 1), '
                        .'"highlights" (array of short strings describing notable sentiment moments)). '
                        .'Use plain language. Preserve names, dates, amounts, and commitments.',
                ],
                [
                    'role' => 'user',
                    'content' => "Analyze this call transcript:\n\n{$transcription}",
                ],
            ],
        ]);

        $raw = trim((string) ($response->choices[0]->message->content ?? ''));
        if ($raw === '') {
            throw new RuntimeException('GPT call analysis returned an empty result.', 502);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('GPT call analysis returned invalid JSON.', 502);
        }

        $summary = trim((string) ($decoded['summary'] ?? ''));
        $sentiment = $this->normalizeSentiment($decoded['sentiment'] ?? null);

        if ($summary === '') {
            throw new RuntimeException('GPT call analysis returned an empty summary.', 502);
        }

        return [
            'summary' => $summary,
            'sentiment' => $sentiment,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySentiment(): array
    {
        return [
            'overall' => 'neutral',
            'score' => 0.0,
            'confidence' => 0.0,
            'customer' => ['label' => 'neutral', 'score' => 0.0],
            'agent' => ['label' => 'neutral', 'score' => 0.0],
            'highlights' => [],
        ];
    }

    /**
     * @param  mixed  $sentiment
     * @return array<string, mixed>
     */
    private function normalizeSentiment(mixed $sentiment): array
    {
        if (! is_array($sentiment)) {
            return $this->emptySentiment();
        }

        $overall = strtolower(trim((string) ($sentiment['overall'] ?? 'neutral')));
        if (! in_array($overall, ['positive', 'neutral', 'negative'], true)) {
            $overall = 'neutral';
        }

        return [
            'overall' => $overall,
            'score' => $this->clampScore($sentiment['score'] ?? 0),
            'confidence' => $this->clampConfidence($sentiment['confidence'] ?? 0),
            'customer' => $this->normalizePartySentiment($sentiment['customer'] ?? null),
            'agent' => $this->normalizePartySentiment($sentiment['agent'] ?? null),
            'highlights' => $this->normalizeHighlights($sentiment['highlights'] ?? []),
        ];
    }

    /**
     * @param  mixed  $party
     * @return array{label: string, score: float}
     */
    private function normalizePartySentiment(mixed $party): array
    {
        if (! is_array($party)) {
            return ['label' => 'neutral', 'score' => 0.0];
        }

        $label = trim((string) ($party['label'] ?? 'neutral'));

        return [
            'label' => $label !== '' ? $label : 'neutral',
            'score' => $this->clampScore($party['score'] ?? 0),
        ];
    }

    /**
     * @param  mixed  $highlights
     * @return list<string>
     */
    private function normalizeHighlights(mixed $highlights): array
    {
        if (! is_array($highlights)) {
            return [];
        }

        $result = [];
        foreach ($highlights as $highlight) {
            $text = trim((string) $highlight);
            if ($text !== '') {
                $result[] = $text;
            }
        }

        return $result;
    }

    private function clampScore(mixed $value): float
    {
        $score = is_numeric($value) ? (float) $value : 0.0;

        return max(-1.0, min(1.0, round($score, 2)));
    }

    private function clampConfidence(mixed $value): float
    {
        $confidence = is_numeric($value) ? (float) $value : 0.0;

        return max(0.0, min(1.0, round($confidence, 2)));
    }
}
