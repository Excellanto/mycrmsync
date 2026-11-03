<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
	public function viewAny(User $user): bool
	{
		return $user->can('users.view');
	}

	public function view(User $user, User $model): bool
	{
		if (!$user->can('users.view')) {
			return false;
		}
		
		// Master can view any user
		if ($user->isMaster()) {
			return true;
		}
		
		// Tenant users can only view users in their tenant
		return $model->tenant_id === $user->tenant_id;
	}

	public function create(User $user): bool
	{
		return $user->can('users.create');
	}

	public function update(User $user, User $model): bool
	{
		if (!$user->can('users.update')) {
			return false;
		}
		
		// Master can update any user
		if ($user->isMaster()) {
			return true;
		}
		
		// Tenant users can only update users in their tenant
		return $model->tenant_id === $user->tenant_id;
	}

	public function delete(User $user, User $model): bool
	{
		if (!$user->can('users.delete')) {
			return false;
		}
		
		// Master can delete any user
		if ($user->isMaster()) {
			return true;
		}
		
		// Tenant users can only delete users in their tenant
		return $model->tenant_id === $user->tenant_id;
	}

	public function impersonate(User $user, User $model): bool
	{
		if ($user->id === $model->id || $model->isMaster()) {
			return false;
		}

		if (! $user->can('users.impersonate')) {
			return false;
		}

		if ($user->isMaster()) {
			return true;
		}

		return $model->tenant_id !== null
			&& (int) $model->tenant_id === (int) $user->tenant_id;
	}
}





