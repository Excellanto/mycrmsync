<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        if (! $user->can('contacts.view')) {
            return false;
        }

        if ($user->isMaster()) {
            return true;
        }

        return (int) $contact->tenant_id === (int) $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        if (! $user->can('contacts.update')) {
            return false;
        }

        if ($user->isMaster()) {
            return true;
        }

        return (int) $contact->tenant_id === (int) $user->tenant_id;
    }

    public function delete(User $user, Contact $contact): bool
    {
        if (! $user->can('contacts.delete')) {
            return false;
        }

        if ($user->isMaster()) {
            return true;
        }

        return (int) $contact->tenant_id === (int) $user->tenant_id;
    }

    public function manageNote(User $user, ContactNote $note): bool
    {
        if (! $user->can('contacts.update')) {
            return false;
        }

        if ($user->isMaster()) {
            return true;
        }

        if ((int) $note->tenant_id !== (int) $user->tenant_id) {
            return false;
        }

        if ($user->hasRole('tenant_admin')) {
            return true;
        }

        return (int) $note->user_id === (int) $user->id;
    }
}
