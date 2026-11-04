<?php

namespace Tests\Unit;

use App\Integrations\Connectors\MyCrmSync\MyCrmSyncContactMapper;
use App\Models\Contact;
use App\Models\User;
use Tests\TestCase;

final class MyCrmSyncContactAssigneeNameTest extends TestCase
{
    public function test_local_contact_includes_the_assigned_user_name(): void
    {
        $contact = new Contact([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'assigned_to' => 9,
        ]);
        $user = new User(['name' => 'Grace Hopper']);
        $user->id = 9;
        $contact->setRelation('assignedUser', $user);

        $mapped = (new MyCrmSyncContactMapper)->mapContact($contact)->toArray();

        $this->assertSame('9', $mapped['assignedTo']);
        $this->assertSame('Grace Hopper', $mapped['assignedToName']);
    }
}
