<?php

namespace App\Http\Controllers;

use App\Services\TenantLogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the profile edit form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $tenant = $user->tenant;

        return Inertia::render('Admin/Profile/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'company_name' => $tenant->company_name,
                'account_type' => $tenant->account_type,
                'pan_card' => $tenant->pan_card,
                'gst_number' => $tenant->gst_number,
                'company_logo_url' => $tenant->companyLogoUrl(),
            ] : null,
        ]);
    }

    /**
     * Update the user's profile.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $tenant = $user->tenant;

        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users')->ignore($user->id)],
        ];

        if ($tenant) {
            $rules['company_name'] = 'required|string|max:255';
            $rules['account_type'] = 'required|string|in:Business,Recruiter';
            $rules['pan_card'] = 'nullable|string|max:20|regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/';
            $rules['gst_number'] = 'nullable|string|max:20|regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]\d[Z][A-Z\d]$/';
        }

        if ($request->filled('password')) {
            $rules['password'] = ['required', 'confirmed', Rules\Password::defaults()];
        }

        $validated = $request->validate($rules);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($request->filled('password')) {
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);
        }

        if ($tenant) {
            $tenant->update([
                'company_name' => $validated['company_name'],
                'account_type' => $validated['account_type'],
                'pan_card' => $validated['pan_card'] ?? null,
                'gst_number' => $validated['gst_number'] ?? null,
                'email' => $validated['email'],
            ]);
        }

        return redirect()->route('admin.profile.edit')->with('success', 'Profile updated successfully.');
    }

    /**
     * Upload company logo (400×100px, 4:1) to tenant storage under Tenant-Profile-Images/.
     */
    public function storeLogo(Request $request, TenantLogoService $logos): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $tenant = $user->tenant;
        abort_if(! $tenant, 403);

        $request->validate([
            'logo' => TenantLogoService::validationRule(),
        ]);

        $logos->store($tenant, $request->file('logo'));

        if ($request->expectsJson()) {
            return response()->json([
                'company_logo_url' => $tenant->companyLogoUrl(),
            ]);
        }

        return redirect()->route('admin.profile.edit')->with('success', 'Company logo updated.');
    }

    /**
     * Remove company logo from tenant storage and tenant record.
     */
    public function destroyLogo(Request $request, TenantLogoService $logos): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $tenant = $user->tenant;
        abort_if(! $tenant, 403);

        $logos->destroy($tenant);

        if ($request->expectsJson()) {
            return response()->json(['company_logo_url' => null]);
        }

        return redirect()->route('admin.profile.edit')->with('success', 'Company logo removed.');
    }
}
