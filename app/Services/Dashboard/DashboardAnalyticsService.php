<?php

namespace App\Services\Dashboard;

use App\Integrations\CrmApiClientResolver;
use App\Models\ActivityLog;
use App\Models\CallLog;
use App\Models\CallRecording;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ApplicationCache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class DashboardAnalyticsService
{
    private const PERIODS = ['7d', '30d', '90d'];

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, string $period): array
    {
        $period = in_array($period, self::PERIODS, true) ? $period : '30d';

        if ($user->isMaster()) {
            return ApplicationCache::rememberDashboardMaster(
                (int) $user->id,
                $period,
                fn (): array => $this->masterAnalytics(
                    $user,
                    $period,
                    ...$this->periodBounds($period),
                ),
            );
        }

        if ($user->tenant_id === null) {
            return $this->emptyTenantPayload($period);
        }

        return ApplicationCache::rememberDashboardTenant(
            (int) $user->tenant_id,
            $period,
            fn (): array => $this->tenantAnalytics(
                (int) $user->tenant_id,
                $period,
                ...$this->periodBounds($period),
            ),
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodBounds(string $period): array
    {
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        $end = now()->endOfDay();
        $start = now()->subDays($days - 1)->startOfDay();

        return [$start, $end];
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantAnalytics(int $tenantId, string $period, Carbon $start, Carbon $end): array
    {
        $tenant = Tenant::query()->find($tenantId);
        $crmSlug = $tenant ? CrmApiClientResolver::slugForTenant($tenant) : '';
        $isMyCrmSync = $tenant && CrmApiClientResolver::isMyCrmSyncTenant($tenant);

        $callQuery = $this->callLogsInPeriod(
            CallLog::query()->forTenantId($tenantId),
            $start,
            $end,
        );

        $totalCalls = (clone $callQuery)->count();
        $totalDuration = (int) (clone $callQuery)->sum('duration_sec');
        $avgDuration = $totalCalls > 0 ? (int) round($totalDuration / $totalCalls) : 0;

        $callsLinked = (clone $callQuery)
            ->whereNotNull('contact_id')
            ->where('contact_id', '!=', '')
            ->count();

        $callsUnmatched = $totalCalls - $callsLinked;

        $directions = (clone $callQuery)
            ->selectRaw('direction, COUNT(*) as count')
            ->groupBy('direction')
            ->orderByDesc('count')
            ->pluck('count', 'direction')
            ->all();

        $callsOverTime = $this->callsGroupedByDay($callQuery, $start, $end);

        $topUsers = $this->topUsersByCalls($callQuery);

        $topNumbers = (clone $callQuery)
            ->selectRaw('COALESCE(phone_e164, phone_raw) as phone, COUNT(*) as count')
            ->where(function ($q) {
                $q->whereNotNull('phone_e164')->where('phone_e164', '!=', '')
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('phone_raw')->where('phone_raw', '!=', '');
                    });
            })
            ->groupBy('phone')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['phone' => (string) $row->phone, 'count' => (int) $row->count])
            ->all();

        $recordingStats = $this->recordingStatsForTenant($tenantId, $start, $end, $totalCalls);
        $sentiment = $this->sentimentBreakdown(
            CallRecording::query()->where('tenant_id', $tenantId),
            $start,
            $end,
        );

        $recentRecordings = CallRecording::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'user_id', 'summary', 'sentiment', 'duration_sec', 'status', 'created_at'])
            ->map(fn (CallRecording $r) => [
                'id' => $r->id,
                'user_name' => $r->user?->name,
                'summary' => $r->summary ? mb_substr($r->summary, 0, 120).(mb_strlen($r->summary) > 120 ? '…' : '') : null,
                'sentiment' => $r->sentiment['overall'] ?? null,
                'duration_sec' => $r->duration_sec,
                'status' => $r->status,
                'created_at' => $r->created_at?->toIso8601String(),
            ])
            ->all();

        $userSyncHealth = $this->userSyncHealth($tenantId, $start, $end);
        $usersNeverSynced = $this->usersNeverSyncedCount($tenantId);

        $contacts = $isMyCrmSync
            ? $this->myCrmSyncContactStats($tenantId, $start, $end, $callQuery)
            : null;

        return [
            'scope' => 'tenant',
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'summary' => [
                'total_calls' => $totalCalls,
                'total_duration_sec' => $totalDuration,
                'avg_duration_sec' => $avgDuration,
                'calls_linked_to_contacts' => $callsLinked,
                'calls_unmatched' => $callsUnmatched,
                'missed_call_rate' => $this->missedRate($directions, $totalCalls),
            ],
            'directions' => $directions,
            'calls_over_time' => $callsOverTime,
            'top_users' => $topUsers,
            'top_numbers' => $topNumbers,
            'recordings' => $recordingStats,
            'sentiment' => $sentiment,
            'recent_recordings' => $recentRecordings,
            'user_sync_health' => $userSyncHealth,
            'users_never_synced' => $usersNeverSynced,
            'integration' => [
                'slug' => $crmSlug,
                'label' => $this->crmLabel($crmSlug),
                'status' => (bool) ($tenant?->integration_status ?? false),
                'is_mycrmsync' => $isMyCrmSync,
            ],
            'contacts' => $contacts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function masterAnalytics(User $user, string $period, Carbon $start, Carbon $end): array
    {
        $callQuery = $this->callLogsInPeriod(CallLog::query(), $start, $end);

        $totalCalls = (clone $callQuery)->count();
        $totalRecordings = CallRecording::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $tenantsActive = Tenant::query()->where('status', 'active')->count();
        $tenantsSuspended = Tenant::query()->where('status', '!=', 'active')->count();
        $totalUsers = User::query()->whereNotNull('tenant_id')->count();

        $tenantsByCrm = Tenant::query()
            ->get(['id', 'integration'])
            ->groupBy(fn (Tenant $t) => CrmApiClientResolver::slugForTenant($t) ?: 'none')
            ->map(fn ($group, $slug) => [
                'slug' => (string) $slug,
                'label' => $this->crmLabel((string) $slug),
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $integrationHealth = Tenant::query()
            ->select('id', 'company_name', 'integration', 'integration_status', 'status')
            ->orderBy('company_name')
            ->get()
            ->map(fn (Tenant $t) => [
                'tenant_id' => $t->id,
                'company_name' => $t->company_name,
                'crm_slug' => CrmApiClientResolver::slugForTenant($t) ?: 'none',
                'crm_label' => $this->crmLabel(CrmApiClientResolver::slugForTenant($t) ?: 'none'),
                'integration_status' => (bool) $t->integration_status,
                'tenant_status' => $t->status,
            ])
            ->all();

        $tenantScorecard = $this->tenantScorecard($start, $end);

        $myCrmSyncPlatform = $this->myCrmSyncPlatformStats();

        $sentiment = $this->sentimentBreakdown(CallRecording::query(), $start, $end);

        $activityLogs = $user->can('activity-logs.view')
            ? $this->activityLogStats($user)
            : null;

        return [
            'scope' => 'master',
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'summary' => [
                'tenants_active' => $tenantsActive,
                'tenants_suspended' => $tenantsSuspended,
                'total_users' => $totalUsers,
                'total_calls' => $totalCalls,
                'total_recordings' => $totalRecordings,
            ],
            'calls_over_time' => $this->callsGroupedByDay($callQuery, $start, $end),
            'directions' => (clone $callQuery)
                ->selectRaw('direction, COUNT(*) as count')
                ->groupBy('direction')
                ->orderByDesc('count')
                ->pluck('count', 'direction')
                ->all(),
            'tenants_by_crm' => $tenantsByCrm,
            'integration_health' => $integrationHealth,
            'tenant_scorecard' => $tenantScorecard,
            'mycrmsync_platform' => $myCrmSyncPlatform,
            'sentiment' => $sentiment,
            'recordings' => $this->recordingStatsPlatform($start, $end, $totalCalls),
            'activity_logs' => $activityLogs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTenantPayload(string $period): array
    {
        return [
            'scope' => 'tenant',
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'summary' => [],
            'directions' => [],
            'calls_over_time' => [],
            'top_users' => [],
            'top_numbers' => [],
            'recordings' => [],
            'sentiment' => [],
            'recent_recordings' => [],
            'user_sync_health' => [],
            'users_never_synced' => 0,
            'integration' => null,
            'contacts' => null,
        ];
    }

    private function callLogDateSql(): string
    {
        return 'COALESCE(started_at, created_at)';
    }

    /**
     * @param  Builder<CallLog>  $query
     * @return Builder<CallLog>
     */
    private function callLogsInPeriod(Builder $query, Carbon $start, Carbon $end): Builder
    {
        $dateSql = $this->callLogDateSql();

        return $query->whereRaw("{$dateSql} >= ?", [$start])
            ->whereRaw("{$dateSql} <= ?", [$end]);
    }

    /**
     * @param  Builder<CallLog>  $query
     * @return list<array{date: string, count: int}>
     */
    private function callsGroupedByDay(Builder $query, Carbon $start, Carbon $end): array
    {
        $dateSql = $this->callLogDateSql();

        $rows = (clone $query)
            ->selectRaw("DATE({$dateSql}) as day, COUNT(*) as count")
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('count', 'day');

        $result = [];
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($cursor->lte($endDay)) {
            $key = $cursor->toDateString();
            $result[] = [
                'date' => $key,
                'count' => (int) ($rows[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $result;
    }

    /**
     * @param  Builder<CallLog>  $query
     * @return list<array{user_id: string, name: string, count: int}>
     */
    private function topUsersByCalls(Builder $query): array
    {
        $rows = (clone $query)
            ->selectRaw('user_id, COUNT(*) as count')
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $userIds = $rows->pluck('user_id')
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter()
            ->all();

        $names = User::query()
            ->whereIn('id', $userIds)
            ->pluck('name', 'id');

        return $rows->map(function ($row) use ($names) {
            $userId = is_numeric($row->user_id) ? (int) $row->user_id : null;

            return [
                'user_id' => (string) $row->user_id,
                'name' => $userId ? ($names[$userId] ?? 'Unknown') : 'Unknown',
                'count' => (int) $row->count,
            ];
        })->all();
    }

    /**
     * @return array<string, int|float|null>
     */
    private function recordingStatsForTenant(int $tenantId, Carbon $start, Carbon $end, int $totalCalls): array
    {
        $recordingQuery = CallRecording::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end]);

        $total = (clone $recordingQuery)->count();
        $totalDuration = (int) (clone $recordingQuery)->sum('duration_sec');

        $completed = (clone $recordingQuery)
            ->where('status', CallRecording::STATUS_COMPLETED)
            ->whereNotNull('transcription')
            ->where('transcription', '!=', '')
            ->count();

        $failed = (clone $recordingQuery)
            ->where('status', CallRecording::STATUS_FAILED)
            ->count();

        $callsWithRecordings = $this->callsWithRecordingsCount(
            CallLog::query()->forTenantId($tenantId),
            $start,
            $end,
        );

        return [
            'total' => $total,
            'total_duration_sec' => $totalDuration,
            'coverage_pct' => $totalCalls > 0 ? round(($callsWithRecordings / $totalCalls) * 100, 1) : 0,
            'calls_with_recordings' => $callsWithRecordings,
            'transcription_completed' => $completed,
            'transcription_failed' => $failed,
            'transcription_pending' => max(0, $total - $completed - $failed),
        ];
    }

    /**
     * @return array<string, int|float|null>
     */
    private function recordingStatsPlatform(Carbon $start, Carbon $end, int $totalCalls): array
    {
        $recordingQuery = CallRecording::query()->whereBetween('created_at', [$start, $end]);

        $total = (clone $recordingQuery)->count();
        $failed = (clone $recordingQuery)->where('status', CallRecording::STATUS_FAILED)->count();
        $completed = (clone $recordingQuery)
            ->where('status', CallRecording::STATUS_COMPLETED)
            ->whereNotNull('transcription')
            ->where('transcription', '!=', '')
            ->count();

        $callsWithRecordings = $this->callsWithRecordingsCount(CallLog::query(), $start, $end);

        return [
            'total' => $total,
            'coverage_pct' => $totalCalls > 0 ? round(($callsWithRecordings / $totalCalls) * 100, 1) : 0,
            'transcription_completed' => $completed,
            'transcription_failed' => $failed,
            'transcription_failure_rate' => $total > 0 ? round(($failed / $total) * 100, 1) : 0,
        ];
    }

    /**
     * @param  Builder<CallLog>  $callQuery
     */
    private function callsWithRecordingsCount(Builder $callQuery, Carbon $start, Carbon $end): int
    {
        $scoped = $this->callLogsInPeriod($callQuery, $start, $end);

        return (clone $scoped)
            ->whereHas('recordings', function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end]);
            })
            ->count();
    }

    /**
     * @param  Builder<CallRecording>  $query
     * @return array<string, int>
     */
    private function sentimentBreakdown(Builder $query, Carbon $start, Carbon $end): array
    {
        $buckets = [
            'positive' => 0,
            'neutral' => 0,
            'negative' => 0,
            'unknown' => 0,
        ];

        (clone $query)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('sentiment')
            ->select(['sentiment'])
            ->cursor()
            ->each(function (CallRecording $recording) use (&$buckets) {
                $overall = strtolower(trim((string) ($recording->sentiment['overall'] ?? 'unknown')));
                if (! array_key_exists($overall, $buckets)) {
                    $overall = 'unknown';
                }
                $buckets[$overall]++;
            });

        return $buckets;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function userSyncHealth(int $tenantId, Carbon $start, Carbon $end): array
    {
        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'last_login_at']);

        $dateSql = $this->callLogDateSql();

        $callStats = CallLog::query()
            ->forTenantId($tenantId)
            ->selectRaw("user_id, COUNT(*) as period_calls, MAX({$dateSql}) as last_call_at")
            ->whereRaw("{$dateSql} >= ?", [$start])
            ->whereRaw("{$dateSql} <= ?", [$end])
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $everSynced = CallLog::query()
            ->forTenantId($tenantId)
            ->selectRaw('user_id, COUNT(*) as total_calls')
            ->groupBy('user_id')
            ->pluck('total_calls', 'user_id');

        return $users->map(function (User $user) use ($callStats, $everSynced, $end) {
            $userKey = (string) $user->id;
            $stats = $callStats->get($userKey);
            $lastCallAt = $stats?->last_call_at;
            $activeThreshold = $end->copy()->subDays(30);

            $isActive = ($user->last_login_at && $user->last_login_at->gte($activeThreshold))
                || ($lastCallAt && Carbon::parse($lastCallAt)->gte($activeThreshold));

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'last_call_sync_at' => $lastCallAt ? Carbon::parse($lastCallAt)->toIso8601String() : null,
                'calls_in_period' => (int) ($stats?->period_calls ?? 0),
                'has_ever_synced' => isset($everSynced[$userKey]) && (int) $everSynced[$userKey] > 0,
                'is_active' => $isActive,
            ];
        })->all();
    }

    private function usersNeverSyncedCount(int $tenantId): int
    {
        $syncedUserIds = CallLog::query()
            ->forTenantId($tenantId)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter()
            ->values()
            ->all();

        return User::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('id', $syncedUserIds)
            ->count();
    }

    /**
     * @param  Builder<CallLog>  $callQuery
     * @return array<string, mixed>
     */
    private function myCrmSyncContactStats(int $tenantId, Carbon $start, Carbon $end, Builder $callQuery): array
    {
        $totalContacts = Contact::query()->forTenantId($tenantId)->count();

        $newContacts = Contact::query()
            ->forTenantId($tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $bySource = Contact::query()
            ->forTenantId($tenantId)
            ->selectRaw("COALESCE(NULLIF(source, ''), 'Unknown') as label, COUNT(*) as count")
            ->groupBy('label')
            ->orderByDesc('count')
            ->limit(8)
            ->pluck('count', 'label')
            ->all();

        $byType = Contact::query()
            ->forTenantId($tenantId)
            ->selectRaw("COALESCE(NULLIF(type, ''), 'Unknown') as label, COUNT(*) as count")
            ->groupBy('label')
            ->orderByDesc('count')
            ->limit(8)
            ->pluck('count', 'label')
            ->all();

        $contactsWithNotes = Contact::query()
            ->forTenantId($tenantId)
            ->whereHas('notes')
            ->count();

        $assignedRows = Contact::query()
            ->forTenantId($tenantId)
            ->selectRaw('assigned_to, COUNT(*) as count')
            ->groupBy('assigned_to')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        $assignedUserIds = $assignedRows->pluck('assigned_to')->filter()->unique()->values();
        $assignedUserNames = $assignedUserIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $assignedUserIds)->pluck('name', 'id');

        $byAssigned = $assignedRows->mapWithKeys(function ($row) use ($assignedUserNames) {
            $label = $row->assigned_to
                ? ($assignedUserNames[$row->assigned_to] ?? 'Unknown')
                : 'Unassigned';

            return [$label => (int) $row->count];
        })->all();

        $callsLinked = (clone $callQuery)
            ->whereNotNull('contact_id')
            ->where('contact_id', '!=', '')
            ->count();

        $contactIdsWithRecordings = CallRecording::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('contact_id')
            ->where('contact_id', '!=', '')
            ->distinct()
            ->count('contact_id');

        $tagCounts = [];
        Contact::query()
            ->forTenantId($tenantId)
            ->whereNotNull('tags')
            ->select(['tags'])
            ->cursor()
            ->each(function (Contact $contact) use (&$tagCounts) {
                foreach ((array) $contact->tags as $tag) {
                    $label = trim((string) $tag);
                    if ($label === '') {
                        continue;
                    }
                    $tagCounts[$label] = ($tagCounts[$label] ?? 0) + 1;
                }
            });
        arsort($tagCounts);
        $topTags = array_slice($tagCounts, 0, 8, true);

        return [
            'total' => $totalContacts,
            'new_in_period' => $newContacts,
            'with_notes' => $contactsWithNotes,
            'without_notes' => max(0, $totalContacts - $contactsWithNotes),
            'by_source' => $bySource,
            'by_type' => $byType,
            'by_assigned' => $byAssigned,
            'calls_linked' => $callsLinked,
            'contacts_with_recordings' => $contactIdsWithRecordings,
            'top_tags' => collect($topTags)->map(fn ($count, $tag) => ['tag' => $tag, 'count' => $count])->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tenantScorecard(Carbon $start, Carbon $end): array
    {
        $dateSql = $this->callLogDateSql();

        $recordingCounts = CallRecording::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('tenant_id, COUNT(*) as recording_count')
            ->groupBy('tenant_id')
            ->pluck('recording_count', 'tenant_id');

        return Tenant::query()
            ->withCount('users')
            ->orderBy('company_name')
            ->get()
            ->map(function (Tenant $tenant) use ($start, $end, $dateSql, $recordingCounts) {
                $tenantId = $tenant->id;
                $slug = CrmApiClientResolver::slugForTenant($tenant);

                $tenantCallQuery = $this->callLogsInPeriod(
                    CallLog::query()->forTenantId($tenantId),
                    $start,
                    $end,
                );

                $lastCallAt = CallLog::query()
                    ->forTenantId($tenantId)
                    ->max(DB::raw($dateSql));

                return [
                    'tenant_id' => $tenantId,
                    'company_name' => $tenant->company_name,
                    'tenant_status' => $tenant->status,
                    'users_count' => $tenant->users_count,
                    'calls_in_period' => (clone $tenantCallQuery)->count(),
                    'recordings_in_period' => (int) ($recordingCounts[$tenantId] ?? 0),
                    'last_activity_at' => $lastCallAt
                        ? Carbon::parse($lastCallAt)->toIso8601String()
                        : null,
                    'integration_status' => (bool) $tenant->integration_status,
                    'crm_slug' => $slug ?: 'none',
                    'crm_label' => $this->crmLabel($slug ?: 'none'),
                ];
            })
            ->sortByDesc('calls_in_period')
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function myCrmSyncPlatformStats(): array
    {
        $myCrmSyncTenantIds = Tenant::query()
            ->get()
            ->filter(fn (Tenant $t) => CrmApiClientResolver::isMyCrmSyncTenant($t))
            ->pluck('id');

        $totalContacts = Contact::query()
            ->whereIn('tenant_id', $myCrmSyncTenantIds)
            ->count();

        $tenantsWithContacts = Contact::query()
            ->whereIn('tenant_id', $myCrmSyncTenantIds)
            ->distinct()
            ->count('tenant_id');

        $tenantCount = $myCrmSyncTenantIds->count();

        return [
            'tenant_count' => $tenantCount,
            'total_contacts' => $totalContacts,
            'tenants_with_zero_contacts' => max(0, $tenantCount - $tenantsWithContacts),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activityLogStats(User $user): array
    {
        $query = ActivityLog::query()->accessibleModules($user);

        $start = now()->subDays(29)->startOfDay();
        $queryForPeriod = (clone $query)->where('created_at', '>=', $start);

        return [
            'total' => (clone $queryForPeriod)->count(),
            'by_module' => (clone $queryForPeriod)
                ->selectRaw('module, COUNT(*) as count')
                ->groupBy('module')
                ->orderByDesc('count')
                ->limit(6)
                ->get()
                ->map(fn ($row) => ['module' => $row->module, 'count' => (int) $row->count])
                ->all(),
            'by_action' => (clone $queryForPeriod)
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(6)
                ->get()
                ->map(fn ($row) => ['action' => $row->action, 'count' => (int) $row->count])
                ->all(),
        ];
    }

    /**
     * @param  array<string, int>  $directions
     */
    private function missedRate(array $directions, int $totalCalls): ?float
    {
        if ($totalCalls === 0) {
            return null;
        }

        $missed = 0;
        foreach ($directions as $direction => $count) {
            if (strtoupper((string) $direction) === 'MISSED') {
                $missed = (int) $count;
                break;
            }
        }

        return round(($missed / $totalCalls) * 100, 1);
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            '7d' => 'Last 7 days',
            '90d' => 'Last 90 days',
            default => 'Last 30 days',
        };
    }

    private function crmLabel(string $slug): string
    {
        return match ($slug) {
            CrmApiClientResolver::SLUG_GOHIGHLEVEL => 'GoHighLevel',
            CrmApiClientResolver::SLUG_ZOHO => 'Zoho',
            CrmApiClientResolver::SLUG_MYCRMSYNC => 'MyCrmSync',
            'none' => 'Not configured',
            default => ucfirst($slug),
        };
    }
}
