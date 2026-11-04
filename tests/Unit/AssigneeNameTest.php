<?php

namespace Tests\Unit;

use App\Integrations\AssigneeName;
use App\Integrations\Connectors\Zoho\ZohoCrmContactMapper;
use App\Integrations\MysimconnectApi\NormalizedCrmContact;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AssigneeNameTest extends TestCase
{
    public function test_name_match_ignores_letter_case_and_keeps_every_id(): void
    {
        $ids = AssigneeName::matchingIds([
            ['id' => '1', 'name' => 'Jane Doe'],
            ['id' => '2', 'name' => 'jane doe'],
            ['id' => '3', 'name' => 'Jane'],
        ], ' Jane Doe ');

        $this->assertSame(['1', '2'], $ids);
    }

    public function test_conflicting_name_params_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        AssigneeName::from([
            'assigned_to_name' => 'Jane Doe',
            'assignedToName' => 'Alex Kim',
        ]);
    }

    public function test_zoho_owner_criteria_combines_ids_and_escapes_the_search_term(): void
    {
        $criteria = AssigneeName::zohoOwnerCriteria(['111', '222'], 'Doe, Jane');

        $this->assertSame(
            '(((Owner:equals:111)or(Owner:equals:222))and((Full_Name:starts_with:Doe\\, Jane)or(First_Name:starts_with:Doe\\, Jane)or(Last_Name:starts_with:Doe\\, Jane)or(Email:starts_with:Doe\\, Jane)or(Phone:starts_with:Doe\\, Jane)))',
            $criteria
        );
    }

    public function test_one_gohighlevel_user_is_a_single_assigned_to_filter(): void
    {
        $this->assertSame([
            'field' => 'assignedTo',
            'operator' => 'eq',
            'value' => 'user_1',
        ], AssigneeName::goHighLevelAssigneeFilter(['user_1']));
    }

    public function test_zoho_contact_keeps_the_owner_id_and_name(): void
    {
        $contact = (new ZohoCrmContactMapper)->mapContact([
            'id' => 'c1',
            'First_Name' => 'Ada',
            'Last_Name' => 'Lovelace',
            'Owner' => ['id' => '999', 'name' => 'Grace Hopper'],
        ])->toArray();

        $this->assertSame('999', $contact['assignedTo']);
        $this->assertSame('Grace Hopper', $contact['assignedToName']);
    }

    public function test_normalized_contact_includes_an_empty_assignee_name(): void
    {
        $this->assertSame('', (new NormalizedCrmContact)->toArray()['assignedToName']);
    }
}
