<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function store(Request $request, User $user, ActivityLogService $activityLog): RedirectResponse
    {
        $actor = $request->user();

        abort_if($actor->id === $user->id, 403);
        abort_if($request->session()->has('impersonator_id'), 403);

        $this->authorize('impersonate', $user);

        if ($user->tenant && ! $user->tenant->allowsLogin()) {
            abort(403, $user->tenant->loginBlockedMessage());
        }

        if (! $user->isMaster() && ! $user->can('admin-panel-access')) {
            abort(403, 'This user cannot access the admin panel.');
        }

        $activityLog->log(
            'users',
            'impersonated',
            $user,
            [
                'impersonator_id' => $actor->id,
                'impersonated_user_id' => $user->id,
            ],
            "{$actor->name} logged in as {$user->name}.",
            $actor,
        );

        $request->session()->put('impersonator_id', $actor->id);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function leave(Request $request, ActivityLogService $activityLog): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');
        abort_unless(is_numeric($impersonatorId), 403);

        $impersonator = User::query()->find($impersonatorId);
        abort_unless($impersonator instanceof User, 403);

        $impersonated = $request->user();

        Auth::guard('web')->login($impersonator);
        $request->session()->regenerate();

        $activityLog->log(
            'users',
            'impersonation-ended',
            $impersonated,
            [
                'impersonator_id' => $impersonator->id,
                'impersonated_user_id' => $impersonated?->id,
            ],
            "{$impersonator->name} returned from {$impersonated?->name}.",
            $impersonator,
        );

        return redirect()->route('admin.users.index');
    }
}
