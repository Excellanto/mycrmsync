<?php

namespace App\Services\VoiceNote;

use Illuminate\Http\UploadedFile;

final class MediaFileTypeResolver
{
    public function isAudio(UploadedFile $file): bool
    {
        $mime = strtolower(trim((string) $file->getMimeType()));

        if ($mime !== '' && str_starts_with($mime, 'audio/')) {
            return true;
        }

        return in_array($mime, ['video/mp4', 'application/ogg'], true);
    }

    public function resolve(UploadedFile $file): string
    {
        $extension = strtolower(ltrim((string) $file->getClientOriginalExtension(), '.'));

        if ($extension !== '') {
            return $extension;
        }

        $mime = strtolower(trim((string) $file->getMimeType()));

        return match ($mime) {
            'audio/mp4', 'audio/m4a', 'audio/x-m4a', 'video/mp4' => 'm4a',
            'audio/mpeg', 'audio/mp3', 'audio/mpga' => 'mp3',
            'audio/wav', 'audio/x-wav', 'audio/wave' => 'wav',
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
            default => $this->isAudio($file) ? 'audio' : 'bin',
        };
    }
}
