<?php

namespace App\Services\VoiceNote;

use App\Services\Integrations\OpenAiConfigService;
use Illuminate\Http\UploadedFile;
use OpenAI;
use RuntimeException;

final class AudioTranscriptionService
{
    public const BACKEND_OPENAI_WHISPER = 'openai_whisper';

    /** @var list<string> */
    private const WHISPER_EXTENSIONS = [
        'flac', 'm4a', 'mp3', 'mp4', 'mpeg', 'mpga', 'oga', 'ogg', 'wav', 'webm',
    ];

    /**
     * @return array{
     *     transcription: string,
     *     duration_sec: int|null,
     *     transcription_backend: string,
     *     transcription_engine_label: string
     * }
     */
    public function transcribe(int $tenantId, UploadedFile $audio, ?string $language = null): array
    {
        $config = OpenAiConfigService::forTenant($tenantId);
        $apiKey = $config->apiKey();

        if ($apiKey === null) {
            throw new RuntimeException('OpenAI is not configured for transcription.', 422);
        }

        $model = $config->whisperModel();
        $client = OpenAI::client($apiKey);
        $whisperPath = $this->prepareWhisperUploadPath($audio);

        $payload = [
            'model' => $model,
            'file' => fopen($whisperPath, 'r'),
            'response_format' => 'verbose_json',
        ];

        $language = strtolower(trim((string) $language));
        if ($language !== '') {
            $payload['language'] = $language;
        }

        try {
            $response = $client->audio()->transcribe($payload);
        } finally {
            if (is_file($whisperPath)) {
                @unlink($whisperPath);
            }
        }

        $duration = $response->duration;

        return [
            'transcription' => trim((string) $response->text),
            'duration_sec' => $duration !== null ? (int) round($duration) : null,
            'transcription_backend' => self::BACKEND_OPENAI_WHISPER,
            'transcription_engine_label' => 'OpenAI '.$model,
        ];
    }

    private function prepareWhisperUploadPath(UploadedFile $audio): string
    {
        $extension = $this->resolveWhisperExtension($audio);
        $tempPath = tempnam(sys_get_temp_dir(), 'voice_note_');

        if ($tempPath === false) {
            throw new RuntimeException('Failed to create a temporary file for transcription.', 500);
        }

        $whisperPath = $tempPath.'.'.$extension;
        if (! @rename($tempPath, $whisperPath)) {
            @unlink($tempPath);
            throw new RuntimeException('Failed to prepare audio file for transcription.', 500);
        }

        $source = $audio->getRealPath();
        if ($source === false || ! @copy($source, $whisperPath)) {
            @unlink($whisperPath);
            throw new RuntimeException('Failed to copy audio file for transcription.', 500);
        }

        return $whisperPath;
    }

    private function resolveWhisperExtension(UploadedFile $audio): string
    {
        $fromName = strtolower(ltrim((string) $audio->getClientOriginalExtension(), '.'));
        if ($this->isWhisperExtension($fromName)) {
            return $fromName;
        }

        $fromMime = $this->extensionFromMime($audio->getMimeType());
        if ($this->isWhisperExtension($fromMime)) {
            return $fromMime;
        }

        $fromMime = $this->extensionFromMime($audio->getClientMimeType());
        if ($this->isWhisperExtension($fromMime)) {
            return $fromMime;
        }

        return 'mp3';
    }

    private function isWhisperExtension(string $extension): bool
    {
        return in_array(strtolower($extension), self::WHISPER_EXTENSIONS, true);
    }

    private function extensionFromMime(?string $mime): string
    {
        $mime = strtolower(trim((string) $mime));

        return match ($mime) {
            'audio/flac', 'audio/x-flac' => 'flac',
            'audio/mp4', 'audio/m4a', 'audio/x-m4a' => 'm4a',
            'audio/mpeg', 'audio/mp3', 'audio/mpga' => 'mp3',
            'video/mp4' => 'mp4',
            'audio/ogg', 'application/ogg', 'audio/x-ogg' => 'ogg',
            'audio/oga' => 'oga',
            'audio/wav', 'audio/x-wav', 'audio/wave' => 'wav',
            'audio/webm' => 'webm',
            default => '',
        };
    }
}
