<?php

namespace App\Integrations\MysimconnectApi;

/**
 * Canonical contact representation for MysimConnect `/api/crm` list and search responses.
 */
final readonly class NormalizedCrmContact
{
    public function __construct(
        public string $id = '',
        public string $name = '',
        public string $firstName = '',
        public string $lastName = '',
        public string $companyName = '',
        public string $businessInfo = '',
        public string $email = '',
        public string $phone = '',
        public string $source = '',
        public string $type = '',
        public string $assignedTo = '',
        public string $city = '',
        public string $state = '',
        public string $postalCode = '',
        public string $address = '',
        public string $dateAdded = '',
        public string $dateUpdated = '',
        public string $dateOfBirth = '',
        /** @var list<string> */
        public array $tags = [],
        public string $country = '',
        public string $website = '',
        public string $timezone = '',
        public string $profilePhoto = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $firstName = self::capitalizeNamePart($this->firstName);
        $lastName = self::capitalizeNamePart($this->lastName);
        $nameSource = trim($this->name) !== ''
            ? $this->name
            : trim(trim($this->firstName).' '.trim($this->lastName));
        $name = self::capitalizeNamePart($nameSource);

        return [
            'id' => $this->id,
            'name' => $name,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'companyName' => $this->companyName,
            'businessInfo' => $this->businessInfo,
            'email' => $this->email,
            'phone' => $this->phone,
            'source' => $this->source,
            'type' => $this->type,
            'assignedTo' => $this->assignedTo,
            'city' => $this->city,
            'state' => $this->state,
            'postalCode' => $this->postalCode,
            'address' => $this->address,
            'dateAdded' => $this->dateAdded,
            'dateUpdated' => $this->dateUpdated,
            'dateOfBirth' => $this->dateOfBirth,
            'tags' => $this->tags,
            'country' => $this->country,
            'website' => $this->website,
            'timezone' => $this->timezone,
            'profilePhoto' => $this->profilePhoto,
        ];
    }

    /**
     * Title-case a contact name segment (first letter of each word uppercase).
     */
    public static function capitalizeNamePart(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return mb_convert_case(mb_strtolower($value, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Normalize vendor tag payloads (string list or {id,name} objects) to tag labels, in API order.
     *
     * @param  list<mixed>  $tags
     * @return list<string>
     */
    public static function normalizeTagList(array $tags): array
    {
        $out = [];

        foreach ($tags as $tag) {
            if (is_array($tag)) {
                $candidate = trim((string) (data_get($tag, 'name') ?: data_get($tag, 'id') ?: ''));
            } else {
                $candidate = trim((string) $tag);
            }

            if ($candidate !== '') {
                $out[] = $candidate;
            }
        }

        return $out;
    }
}
