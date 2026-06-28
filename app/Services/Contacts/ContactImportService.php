<?php

namespace App\Services\Contacts;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

final class ContactImportService
{
    /**
     * @var array<string, list<string>>
     */
    private const HEADER_ALIASES = [
        'first_name' => ['first_name', 'firstname', 'first name', 'first'],
        'last_name' => ['last_name', 'lastname', 'last name', 'last', 'surname'],
        'name' => ['name', 'full name', 'fullname', 'contact name', 'contact'],
        'email' => ['email', 'e-mail', 'email address', 'mail'],
        'phone' => ['phone', 'mobile', 'telephone', 'phone number', 'cell', 'tel'],
        'company_name' => ['company', 'company_name', 'company name', 'organization', 'organisation', 'business'],
        'tags' => ['tags', 'tag', 'labels', 'label'],
        'source' => ['source', 'lead source'],
        'city' => ['city', 'town'],
        'state' => ['state', 'province', 'region'],
        'country' => ['country'],
        'address' => ['address', 'street', 'street address'],
        'postal_code' => ['postal_code', 'postal code', 'zip', 'zip code', 'postcode', 'pincode', 'pin code'],
        'website' => ['website', 'url', 'web'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function parseFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: '');

        if (! in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
            throw ValidationException::withMessages([
                'file' => ['Upload a CSV or Excel file (.csv, .xlsx, .xls).'],
            ]);
        }

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (ReaderException $e) {
            throw ValidationException::withMessages([
                'file' => ['Could not read the file. Check that it is a valid CSV or Excel document.'],
            ]);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, false);

        if ($matrix === []) {
            return [];
        }

        $headerRow = array_shift($matrix);
        $columnMap = $this->mapHeaders($headerRow);

        if ($columnMap === []) {
            throw ValidationException::withMessages([
                'file' => ['No recognizable column headers were found. Include columns such as Name, Email, or Phone.'],
            ]);
        }

        $rows = [];

        foreach ($matrix as $offset => $cells) {
            $parsed = $this->parseRow($cells, $columnMap, (int) $offset);

            if ($parsed === null) {
                continue;
            }

            $rows[] = $parsed;
        }

        return $rows;
    }

    /**
     * @param  list<mixed>  $headerRow
     * @return array<string, int>
     */
    private function mapHeaders(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);

            if ($normalized === '') {
                continue;
            }

            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (in_array($normalized, $aliases, true) && ! array_key_exists($field, $map)) {
                    $map[$field] = (int) $index;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @param  list<mixed>  $cells
     * @param  array<string, int>  $columnMap
     * @return array<string, mixed>|null
     */
    private function parseRow(array $cells, array $columnMap, int $offset): ?array
    {
        $data = [];

        foreach ($columnMap as $field => $index) {
            $value = trim((string) ($cells[$index] ?? ''));

            if ($value !== '') {
                $data[$field] = $value;
            }
        }

        if (isset($data['name']) && ! isset($data['first_name']) && ! isset($data['last_name'])) {
            [$first, $last] = $this->splitName($data['name']);
            $data['first_name'] = $first;
            $data['last_name'] = $last;
            unset($data['name']);
        }

        if ($data === []) {
            return null;
        }

        if (isset($data['tags'])) {
            $data['tags'] = $this->parseTags($data['tags']);
        }

        $warnings = [];
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $warnings[] = 'Invalid email format.';
        }

        if ($email === '' && $phone === '' && $firstName === '' && $lastName === '') {
            $warnings[] = 'Missing name, email, and phone.';
        }

        $displayName = trim($firstName.' '.$lastName);

        if ($displayName === '') {
            $displayName = $email !== '' ? $email : ($phone !== '' ? $phone : 'Row '.($offset + 2));
        }

        return [
            'index' => $offset,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'company_name' => trim((string) ($data['company_name'] ?? '')),
            'source' => trim((string) ($data['source'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'state' => trim((string) ($data['state'] ?? '')),
            'country' => trim((string) ($data['country'] ?? '')),
            'address' => trim((string) ($data['address'] ?? '')),
            'postal_code' => trim((string) ($data['postal_code'] ?? '')),
            'website' => trim((string) ($data['website'] ?? '')),
            'tags' => $data['tags'] ?? [],
            'name' => $displayName,
            'warnings' => $warnings,
            'importable' => $warnings === [],
        ];
    }

    private function normalizeHeader(string $header): string
    {
        $header = Str::lower(trim($header));
        $header = preg_replace('/[\x{FEFF}]/u', '', $header) ?? $header;
        $header = str_replace(['_', '-'], ' ', $header);
        $header = preg_replace('/\s+/', ' ', $header) ?? $header;

        return trim($header);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [];

        return [
            trim((string) ($parts[0] ?? '')),
            trim((string) ($parts[1] ?? '')),
        ];
    }

    /**
     * @return list<string>
     */
    private function parseTags(string $value): array
    {
        $parts = preg_split('/[,;|]/', $value) ?: [];

        return array_values(array_unique(array_filter(array_map(
            fn (string $tag): string => trim($tag),
            $parts,
        ))));
    }
}
