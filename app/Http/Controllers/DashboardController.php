<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardAnalyticsService $analytics,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $period = $request->query('period', '30d');
        $analytics = $this->analytics->build($user, (string) $period);

        return Inertia::render('Admin/Dashboard', [
            'analytics' => $analytics,
            'period' => $analytics['period'] ?? '30d',
        ]);
    }
}
