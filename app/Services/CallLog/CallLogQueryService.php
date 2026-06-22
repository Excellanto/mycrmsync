<?php

namespace App\Services\CallLog;

use App\Models\CallLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class CallLogQueryService
{
    /**
     * @return array{items: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function paginate(
        Builder $query,
        int $limit,
        ?string $cursor = null,
    ): array {
        if ($cursor !== null && $cursor !== '') {
            [$cursorStartedAt, $cursorId] = $this->decodeCursor($cursor);

            if ($cursorStartedAt === null) {
                $query->whereNull('started_at')
                    ->where('id', '<', $cursorId);
            } else {
                $query->where(function ($builder) use ($cursorStartedAt, $cursorId) {
                    $builder->where('started_at', '<', $cursorStartedAt)
                        ->orWhere(function ($inner) use ($cursorStartedAt, $cursorId) {
                            $inner->where('started_at', '=', $cursorStartedAt)
                                ->where('id', '<', $cursorId);
                        });
                });
            }
        }

        $rows = $query
            ->orderByRaw('started_at desc nulls last')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $page = $rows->take($limit);
        $items = $page
            ->map(fn (CallLog $row) => $this->serializeCallLog($row))
            ->values()
            ->all();

        $nextCursor = null;
        if ($hasMore) {
            $last = $page->last();
            if ($last instanceof CallLog) {
                $nextCursor = $this->encodeCursor($last);
            }
        }

        return [
            'items' => $items,
            'next_cursor' => $nextCursor,
        ];
    }

    public function baseQuery(string $locationId): Builder
    {
        $query = CallLog::query()
            ->with(['user:id,name,email,tenant_id']);

        if ($locationId !== '') {
            $query->where(function ($builder) use ($locationId) {
                $builder->where('location_id', $locationId)
                    ->orWhereNull('location_id');
            });
        }

        return $query;
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', (string) $user->id);
    }

    public function scopeForTenant(Builder $query, User $anchorUser): Builder
    {
        if ($anchorUser->tenant_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->forTenantId((int) $anchorUser->tenant_id);
    }

    public function scopeDateRange(Builder $query, ?string $dateFrom, ?string $dateTo): Builder
    {
        if ($dateFrom !== null && $dateFrom !== '') {
            $query->whereRaw('DATE(COALESCE(started_at, created_at)) >= ?', [$dateFrom]);
        }

        if ($dateTo !== null && $dateTo !== '') {
            $query->whereRaw('DATE(COALESCE(started_at, created_at)) <= ?', [$dateTo]);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeCallLog(CallLog $row): array
    {
        return [
            'id' => (string) $row->id,
            'user_id' => (string) $row->user_id,
            'user_name' => $row->user?->name,
            'location_id' => $row->location_id,
            'direction' => $row->direction,
            'phone_raw' => $row->phone_raw,
            'phone_e164' => $row->phone_e164,
            'contact_id' => $row->contact_id,
            'contact_name' => $row->contact_name,
            'duration_sec' => $row->duration_sec,
            'started_at' => $row->started_at?->toIso8601String(),
            'ended_at' => $row->ended_at?->toIso8601String(),
            'sim_account_id' => $row->sim_account_id,
            'status' => $row->status,
            'sync_fingerprint' => $row->sync_fingerprint,
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }

    public function encodeCursor(CallLog $row): string
    {
        $startedAt = $row->started_at instanceof Carbon
            ? $row->started_at->toIso8601String()
            : '';

        return base64_encode($startedAt.'|'.(string) $row->id);
    }

    /**
     * @return array{0: Carbon|null, 1: string}
     */
    public function decodeCursor(string $cursor): array
    {
        $decoded = base64_decode($cursor, true);
        if ($decoded === false || ! str_contains($decoded, '|')) {
            throw ValidationException::withMessages([
                'cursor' => ['The cursor is invalid.'],
            ]);
        }

        [$startedAt, $id] = explode('|', $decoded, 2);
        $startedAt = trim($startedAt);
        $id = trim($id);

        if ($id === '') {
            throw ValidationException::withMessages([
                'cursor' => ['The cursor is invalid.'],
            ]);
        }

        return [
            $startedAt !== '' ? Carbon::parse($startedAt) : null,
            $id,
        ];
    }
}
