<?php

namespace App\Integrations\Connectors\Zoho;

use App\Integrations\MysimconnectApi\NormalizedCrmContact;

/**
 * Map Zoho CRM Contacts module payloads to MysimConnect normalized contact JSON.
 */
final class ZohoCrmContactMapper
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function mapContact(array $row): NormalizedCrmContact
    {
        $tags = data_get($row, 'Tag', []);
        $tagList = is_array($tags) ? $tags : [];

        $account = data_get($row, 'Account_Name');
        $companyName = '';
        if (is_array($account)) {
            $companyName = trim((string) data_get($account, 'name', ''));
        } elseif (is_string($account)) {
            $companyName = trim($account);
        }

        $owner = data_get($row, 'Owner');
        $assignedTo = '';
        if (is_array($owner)) {
            $assignedTo = trim((string) (data_get($owner, 'id') ?: data_get($owner, 'name') ?: ''));
        } elseif (is_string($owner)) {
            $assignedTo = trim($owner);
        }

        $firstName = trim((string) data_get($row, 'First_Name', ''));
        $lastName = trim((string) data_get($row, 'Last_Name', ''));
        $name = trim((string) (data_get($row, 'Full_Name') ?: ''));
        if ($name === '') {
            $name = trim($firstName.' '.$lastName);
        }

        return new NormalizedCrmContact(
            id: trim((string) data_get($row, 'id', '')),
            name: $name,
            firstName: $firstName,
            lastName: $lastName,
            companyName: $companyName,
            businessInfo: '',
            email: trim((string) data_get($row, 'Email', '')),
            phone: trim((string) (data_get($row, 'Phone') ?: data_get($row, 'Mobile') ?: '')),
            source: trim((string) data_get($row, 'Lead_Source', '')),
            type: trim((string) data_get($row, 'Contact_Type', '')),
            assignedTo: $assignedTo,
            city: trim((string) data_get($row, 'Mailing_City', '')),
            state: trim((string) data_get($row, 'Mailing_State', '')),
            postalCode: trim((string) data_get($row, 'Mailing_Zip', '')),
            address: trim((string) data_get($row, 'Mailing_Street', '')),
            dateAdded: trim((string) data_get($row, 'Created_Time', '')),
            dateUpdated: trim((string) data_get($row, 'Modified_Time', '')),
            dateOfBirth: trim((string) data_get($row, 'Date_of_Birth', '')),
            tags: NormalizedCrmContact::normalizeTagList($tagList),
            country: trim((string) data_get($row, 'Mailing_Country', '')),
            website: trim((string) data_get($row, 'Website', '')),
            timezone: '',
            profilePhoto: '',
        );
    }
}
