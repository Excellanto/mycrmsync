<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\CallRecording;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CallRecording\CallRecordingProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CallLogController extends Controller
{
    public function __construct(
        private CallRecordingProcessingService $recordingFormatter,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CallLog::class);

        $user = Auth::user();
        $isMaster = $user->isMaster();

        $query = CallLog::query()
            ->with([
                'user:id,name,email,tenant_id',
                'user.tenant:id,company_name',
                'recordings' => fn ($q) => $q->orderByDesc('created_at')->limit(1),
            ])
            ->withCount('recordings')
            ->orderByRaw('started_at desc nulls last')
            ->orderByDesc('created_at');

        if (! $isMaster) {
            $query->forTenantId((int) $user->tenant_id);
        } elseif ($request->filled('tenant_id')) {
            $query->forTenantId($request->integer('tenant_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (string) $request->integer('user_id'));
        }

        if ($request->filled('phone')) {
            $phone = $request->string('phone')->toString();
            $query->where(function ($q) use ($phone) {
                $q->where('phone_raw', 'ilike', "%{$phone}%")
                    ->orWhere('phone_e164', 'ilike', "%{$phone}%");
            });
        }

        if ($request->filled('contact')) {
            $contact = $request->string('contact')->toString();
            $query->where(function ($q) use ($contact) {
                $q->where('contact_name', 'ilike', "%{$contact}%")
                    ->orWhere('contact_id', 'ilike', "%{$contact}%");
            });
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->string('direction')->toString());
        }

        if ($request->filled('start_date')) {
            $query->whereDate('started_at', '>=', $request->string('start_date')->toString());
        }

        if ($request->filled('end_date')) {
            $query->whereDate('started_at', '<=', $request->string('end_date')->toString());
        }

        if ($request->boolean('has_recording')) {
            $query->whereHas('recordings');
        }

        $logs = $query->paginate(20)->withQueryString();

        $usersQuery = User::query()
            ->select('id', 'name', 'email', 'tenant_id')
            ->orderBy('name');

        if (! $isMaster) {
            $usersQuery->where('tenant_id', $user->tenant_id);
        } elseif ($request->filled('tenant_id')) {
            $usersQuery->where('tenant_id', $request->integer('tenant_id'));
        }

        return Inertia::render('Admin/CallLogs/Index', [
            'logs' => $logs->through(fn (CallLog $log) => $this->callLogPayload($log)),
            'filters' => $request->only(['tenant_id', 'user_id', 'phone', 'contact', 'direction', 'start_date', 'end_date', 'has_recording']),
            'tenants' => $isMaster
                ? Tenant::query()->select('id', 'company_name')->orderBy('company_name')->get()
                : null,
            'users' => $usersQuery->get(),
            'directions' => ['INCOMING', 'OUTGOING', 'MISSED', 'UNKNOWN'],
        ]);
    }

    public function recordings(CallLog $callLog): JsonResponse
    {
        $this->authorize('view', $callLog);

        $recordings = CallRecording::query()
            ->where('call_log_id', $callLog->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CallRecording $recording) => $this->recordingFormatter->formatResult($recording));

        return response()->json([
            'call_log' => [
                'id' => $callLog->id,
                'contact_name' => $callLog->contact_name,
                'phone_e164' => $callLog->phone_e164,
                'phone_raw' => $callLog->phone_raw,
                'started_at' => $callLog->started_at?->toIso8601String(),
                'direction' => $callLog->direction,
                'duration_sec' => $callLog->duration_sec,
            ],
            'recordings' => $recordings->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function callLogPayload(CallLog $log): array
    {
        $latest = $log->recordings->first();

        return [
            'id' => $log->id,
            'location_id' => $log->location_id,
            'user_id' => $log->user_id,
            'direction' => $log->direction,
            'phone_raw' => $log->phone_raw,
            'phone_e164' => $log->phone_e164,
            'contact_id' => $log->contact_id,
            'contact_name' => $log->contact_name,
            'duration_sec' => $log->duration_sec,
            'started_at' => $log->started_at,
            'ended_at' => $log->ended_at,
            'status' => $log->status,
            'created_at' => $log->created_at,
            'recordings_count' => (int) ($log->recordings_count ?? $log->recordings->count()),
            'user' => $log->user,
            'latest_recording' => $latest !== null ? $this->recordingSummary($latest) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recordingSummary(CallRecording $recording): array
    {
        return [
            'id' => $recording->id,
            'status' => $recording->status,
            'has_transcription' => trim((string) ($recording->transcription ?? '')) !== '',
            'has_summary' => trim((string) ($recording->summary ?? '')) !== '',
            'transcription_backend' => $recording->transcription_backend,
            'created_at' => $recording->created_at?->toIso8601String(),
        ];
    }
}
