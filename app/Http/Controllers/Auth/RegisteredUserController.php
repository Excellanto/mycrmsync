<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'company_name' => 'required|string|max:255',
            'account_type' => 'required|string|in:Business,Recruiter',
            'pan_card' => 'nullable|string|max:20|regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/',
            'gst_number' => 'nullable|string|max:20|regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]\d[Z][A-Z\d]$/',
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Create tenant first
        $tenant = Tenant::create([
            'company_name' => $request->company_name,
            'account_type' => $request->account_type,
            'email' => $request->email,
            'pan_card' => $request->pan_card,
            'gst_number' => $request->gst_number,
            'status' => 'new',
        ]);

        // Create user with tenant_id
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tenant_id' => $tenant->id,
        ]);

        $tenantAdminRole = Role::where('slug', 'tenant_admin')->where('guard_name', 'web')->firstOrFail();
        $user->assignRole($tenantAdminRole);

        event(new Registered($user));

        return redirect()
            ->route('login')
            ->with('status', 'Registration successful. Your account is pending activation by an administrator.');
    }
}
