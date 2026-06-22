<?php

namespace App\Services\VoiceNote;

use App\Services\Integrations\StorageConfigService;
use App\Services\Integrations\TenantStorageDiskService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class VoiceNoteStorageService
{
    /**
     * @return array{
     *     storage_path: string,
     *     file_name: string,
     *     recording_url_long: string
     * }
     */
    public function upload(
        int $tenantId,
        string $userId,
        UploadedFile $audio,
    ): array {
        $storage = StorageConfigService::forTenant($tenantId);
        $provider = $storage->voiceNoteStorageProvider();

        if ($provider === StorageConfigService::PROVIDER_SUPABASE) {
            return $this->uploadToSupabase($storage, $userId, $audio);
        }

        if ($provider === StorageConfigService::PROVIDER_R2) {
            return $this->uploadToR2($storage, $tenantId, $userId, $audio);
        }

        throw new RuntimeException(
            'No storage provider is configured. Set Supabase or R2 credentials in tenant settings or .env.',
            422
        );
    }

    /**
     * @return array{storage_path: string, file_name: string, recording_url_long: string}
     */
    private function uploadToSupabase(
        StorageConfigService $storage,
        string $userId,
        UploadedFile $audio,
    ): array {
        $baseUrl = rtrim((string) $storage->supabaseUrl(), '/');
        $apiKey = (string) $storage->supabaseKey();
        $bucket = $storage->voicenotesBucket();

        $fileName = $this->buildFileName($audio);
        $safeUserId = $this->sanitizePathSegment($userId);
        $storagePath = "{$safeUserId}/{$fileName}";

        $uploadUrl = "{$baseUrl}/storage/v1/object/{$bucket}/{$storagePath}";
        $mimeType = $audio->getMimeType() ?: 'audio/*';

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'apikey' => $apiKey,
            'Content-Type' => $mimeType,
            'x-upsert' => 'true',
        ])->withBody(
            (string) file_get_contents($audio->getRealPath()),
            $mimeType
        )->post($uploadUrl);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Failed to upload voice note to Supabase Storage: '.$this->extractErrorMessage($response->json(), $response->body()),
                $response->status() >= 400 && $response->status() < 600 ? $response->status() : 502
            );
        }

        $recordingUrlLong = "{$baseUrl}/storage/v1/object/public/{$bucket}/{$storagePath}";

        return [
            'storage_path' => $storagePath,
            'file_name' => $fileName,
            'recording_url_long' => $recordingUrlLong,
        ];
    }

    /**
     * @return array{storage_path: string, file_name: string, recording_url_long: string}
     */
    private function uploadToR2(
        StorageConfigService $storage,
        int $tenantId,
        string $userId,
        UploadedFile $audio,
    ): array {
        $fileName = $this->buildFileName($audio);
        $safeUserId = $this->sanitizePathSegment($userId);
        $storagePath = "voicenotes/{$safeUserId}/{$fileName}";

        $disk = TenantStorageDiskService::diskForTenant($tenantId);
        $stored = $disk->put($storagePath, (string) file_get_contents($audio->getRealPath()));

        if ($stored === false || ! $disk->exists($storagePath)) {
            throw new RuntimeException('Failed to upload voice note to R2 storage.', 502);
        }

        $recordingUrlLong = $storage->r2PublicFileUrl($storagePath) ?? $disk->url($storagePath);
        if ($recordingUrlLong === null || $recordingUrlLong === '') {
            throw new RuntimeException('Failed to resolve a public URL for the uploaded voice note.', 502);
        }

        return [
            'storage_path' => $storagePath,
            'file_name' => $fileName,
            'recording_url_long' => $recordingUrlLong,
        ];
    }

    private function buildFileName(UploadedFile $audio): string
    {
        $extension = $audio->getClientOriginalExtension() ?: $this->extensionFromMime($audio->getMimeType());
        $mime = strtolower((string) $audio->getMimeType());
        $isAudio = $mime !== '' && (str_starts_with($mime, 'audio/') || in_array($mime, ['video/mp4', 'application/ogg'], true));
        $prefix = $isAudio ? 'recording' : 'attachment';

        return $prefix.'_'.time().'_'.uniqid('', true).'.'.ltrim($extension, '.');
    }

    private function sanitizePathSegment(string $value): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]+/', '_', trim($value)) ?? 'unknown';

        return $sanitized !== '' ? $sanitized : 'unknown';
    }

    private function extensionFromMime(?string $mime): string
    {
        return match ($mime) {
            'audio/mp4', 'audio/m4a', 'audio/x-m4a', 'video/mp4' => 'm4a',
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/webm' => 'webm',
            'audio/ogg', 'application/ogg' => 'ogg',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            default => 'bin',
        };
    }

    /**
     * @param  mixed  $json
     */
    private function extractErrorMessage(mixed $json, string $fallback): string
    {
        if (is_array($json)) {
            foreach (['message', 'error', 'error_description', 'msg'] as $key) {
                if (isset($json[$key]) && is_string($json[$key]) && $json[$key] !== '') {
                    return $json[$key];
                }
            }
        }

        return $fallback !== '' ? $fallback : 'Unknown error';
    }
}
