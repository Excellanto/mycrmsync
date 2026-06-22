<?php

namespace App\Services\ShortUrl;

use App\Models\ShortUrl;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use RuntimeException;

final class ShortUrlService
{
    public function create(
        string $longUrl,
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $sourceType = null,
        ?string $sourceId = null,
    ): ShortUrl {
        $longUrl = trim($longUrl);
        if ($longUrl === '') {
            throw new RuntimeException('A long URL is required to create a short URL.', 422);
        }

        $attempts = 0;
        while ($attempts < 10) {
            $code = $this->generateCode();

            try {
                return ShortUrl::query()->create([
                    'code' => $code,
                    'long_url' => $longUrl,
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ]);
            } catch (QueryException $e) {
                if (! $this->isDuplicateCode($e)) {
                    throw $e;
                }
            }

            $attempts++;
        }

        throw new RuntimeException('Failed to generate a unique short URL code.', 500);
    }

    public function findByCode(string $code): ?ShortUrl
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        return ShortUrl::query()->where('code', $code)->first();
    }

    public function attachSource(ShortUrl $shortUrl, string $sourceType, string $sourceId): ShortUrl
    {
        $shortUrl->update([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);

        return $shortUrl->refresh();
    }

    private function generateCode(): string
    {
        return Str::upper(Str::random(8));
    }

    private function isDuplicateCode(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
