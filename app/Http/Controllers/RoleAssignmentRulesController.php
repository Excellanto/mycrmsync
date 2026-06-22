<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RoleAssignmentRules;
use Inertia\Inertia;

class RoleAssignmentRulesController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', User::class);

        return Inertia::render('Admin/RoleAssignmentRules/Index', [
            'sections' => RoleAssignmentRules::catalogSections(),
        ]);
    }
}
