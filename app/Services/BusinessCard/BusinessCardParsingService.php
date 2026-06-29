<?php

namespace App\Services\BusinessCard;

use App\Services\Integrations\OpenAiConfigService;
use Illuminate\Http\UploadedFile;
use OpenAI;
use RuntimeException;

final class BusinessCardParsingService
{
    public const MODEL = 'gpt-4o-mini';

    /** @var list<string> */
    private const FIELDS = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'company_name',
        'job_title',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'website',
    ];

    /**
     * @return array<string, string|null>
     */
    public function parse(int $tenantId, UploadedFile $image, string $locale = 'en_IN'): array
    {
        $config = OpenAiConfigService::forTenant($tenantId);
        $apiKey = $config->apiKey();

        if ($apiKey === null) {
            throw new RuntimeException('OpenAI is not configured for business card parsing.', 422);
        }

        $mime = $image->getMimeType() ?: 'image/jpeg';
        $contents = file_get_contents($image->getRealPath() ?: $image->getPathname());

        if ($contents === false || $contents === '') {
            throw new RuntimeException('Could not read the uploaded image.', 422);
        }

        $dataUrl = 'data:'.$mime.';base64,'.base64_encode($contents);
        $locale = trim($locale) !== '' ? trim($locale) : 'en_IN';

        $client = OpenAI::client($apiKey);

        $response = $client->chat()->create([
            'model' => self::MODEL,
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You extract structured contact details from business card photos for a CRM mobile app. '
                        .'Return JSON with exactly these keys: '
                        .'first_name, last_name, email, phone, company_name, job_title, address, city, state, postal_code, country, website. '
                        .'Use null for any field that is missing or unreadable. '
                        .'Normalize phone numbers to E.164 when possible (include country code). '
                        .'Use full country names (e.g. India, not IN). '
                        .'Split person names into first_name and last_name when possible. '
                        .'Do not invent information that is not visible on the card.',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Extract contact fields from this business card. User locale: {$locale}.",
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $dataUrl,
                                'detail' => 'high',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $raw = trim((string) ($response->choices[0]->message->content ?? ''));
        if ($raw === '') {
            throw new RuntimeException('Business card parsing returned an empty result.', 502);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Business card parsing returned invalid JSON.', 502);
        }

        return $this->normalizeParsedFields($decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, string|null>
     */
    private function normalizeParsedFields(array $decoded): array
    {
        $normalized = [];

        foreach (self::FIELDS as $field) {
            $value = $decoded[$field] ?? null;

            if ($value === null) {
                $normalized[$field] = null;

                continue;
            }

            $stringValue = trim((string) $value);
            $normalized[$field] = $stringValue !== '' ? $stringValue : null;
        }

        return $normalized;
    }
}
