<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShortUrl;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class UrlManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ShortUrl::class);

        $user = Auth::user();
        $isMaster = $user->isMaster();

        $query = ShortUrl::query()
            ->with([
                'user:id,name,email',
                'tenant:id,company_name',
            ])
            ->latest();

        if (! $isMaster) {
            $query->forTenantId((int) $user->tenant_id);
        } elseif ($request->filled('tenant_id')) {
            $query->forTenantId($request->integer('tenant_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('code', 'ilike', "%{$search}%")
                    ->orWhere('long_url', 'ilike', "%{$search}%")
                    ->orWhere('source_type', 'ilike', "%{$search}%")
                    ->orWhere('source_id', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $shortUrls = $query->paginate(20)->withQueryString();

        $users = User::query()
            ->when(! $isMaster, fn ($q) => $q->where('tenant_id', $user->tenant_id))
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        $tenants = $isMaster
            ? Tenant::query()->select('id', 'company_name')->orderBy('company_name')->get()
            : null;

        return Inertia::render('Admin/UrlManagement/Index', [
            'shortUrls' => $shortUrls,
            'filters' => $request->only(['search', 'tenant_id', 'user_id']),
            'users' => $users,
            'tenants' => $tenants,
            'isMaster' => $isMaster,
        ]);
    }
}
