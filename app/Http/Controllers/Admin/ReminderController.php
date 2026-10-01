<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactReminder;
use App\Models\User;
use App\Services\Contacts\ContactReminderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReminderController extends Controller
{
    public function __construct(
        private readonly ContactReminderService $reminders,
    ) {}

    public function complete(ContactReminder $reminder): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->reminders->assertCanManage($user, $reminder);

        $updated = $this->reminders->complete($reminder);

        return response()->json([
            'reminder' => $this->reminders->payload($updated),
        ]);
    }

    public function dismiss(ContactReminder $reminder): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->reminders->assertCanManage($user, $reminder);

        $updated = $this->reminders->dismiss($reminder);

        return response()->json([
            'reminder' => $this->reminders->payload($updated),
        ]);
    }

    public function snooze(Request $request, ContactReminder $reminder): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->reminders->assertCanManage($user, $reminder);

        $data = $request->validate([
            'minutes' => ['required_without:reminder_at', 'nullable', 'integer', 'min:1', 'max:43200'],
            'reminder_at' => ['required_without:minutes', 'nullable', 'date', 'after:now'],
        ]);

        $remindAt = ! empty($data['reminder_at'])
            ? Carbon::parse($data['reminder_at'])
            : now()->addMinutes((int) $data['minutes']);

        $updated = $this->reminders->snooze($reminder, $remindAt);

        return response()->json([
            'reminder' => $this->reminders->payload($updated),
        ]);
    }
}
