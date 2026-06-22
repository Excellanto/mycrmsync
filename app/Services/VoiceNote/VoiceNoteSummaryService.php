<?php

namespace App\Services\VoiceNote;

use App\Services\Integrations\OpenAiConfigService;
use OpenAI;
use RuntimeException;

final class VoiceNoteSummaryService
{
    public function summarize(int $tenantId, string $transcription): string
    {
        $transcription = trim($transcription);
        if ($transcription === '') {
            return '';
        }

        $config = OpenAiConfigService::forTenant($tenantId);
        $apiKey = $config->apiKey();

        if ($apiKey === null) {
            throw new RuntimeException('OpenAI is not configured for summarization.', 422);
        }

        $model = $config->gptModel();
        $client = OpenAI::client($apiKey);

        $response = $client->chat()->create([
            'model' => $model,
            'temperature' => 0.3,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You summarize CRM voice-note transcripts into concise, action-oriented notes for sales reps. '
                        .'Use plain language, bullet points when helpful, and preserve names, dates, amounts, and next steps.',
                ],
                [
                    'role' => 'user',
                    'content' => "Summarize this voice note transcript for a CRM contact note:\n\n{$transcription}",
                ],
            ],
        ]);

        $summary = trim((string) ($response->choices[0]->message->content ?? ''));

        if ($summary === '') {
            throw new RuntimeException('GPT summarization returned an empty result.', 502);
        }

        return $summary;
    }
}
