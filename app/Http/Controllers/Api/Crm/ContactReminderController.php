<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Api\Crm\Concerns\ResolvesCrmApiContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ContactReminder;
use App\Models\User;
use App\Services\Contacts\ContactReminderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group CRM
 *
 * Contact reminders stored in MysimConnect. They work for MyCrmSync, GoHighLevel, and Zoho contacts.
 * A reminder can be attached to a note (`noteId`) or stand on the contact alone.
 *
 * Requires `Authorization: Bearer <token>` from email OTP verify. `user_id` must match the authenticated user.
 *
 * @authenticated
 */
final class ContactReminderController extends Controller
{
    use ResolvesCrmApiContext;

    public function __construct(
        private readonly ContactReminderService $reminders,
    ) {}

    /**
     * List my reminders
     *
     * Pending reminders for the authenticated user. Tenant admins receive every pending reminder in the tenant.
     * Pass `contactId` to limit the list to one contact.
     *
     * @queryParam user_id int required Must match the authenticated user. Example: 1
     * @queryParam contactId string Optional CRM contact id. Example: 550e8400-e29b-41d4-a716-446655440000
     *
     * @response 200 {"success":true,"message":"OK","data":{"reminders":[{"id":"6ba7b810-9dad-11d1-80b4-00c04fd430c8","contact_id":"550e8400-e29b-41d4-a716-446655440000","contact_note_id":null,"contact_name":"Jane Doe","note_excerpt":"Call back about the quote","remind_at":"2026-10-03T10:00:00+00:00","status":"pending","user_id":1,"user_name":"Alex","local_contact":true}]}}
     */
    public function index(Request $request): JsonResponse
    {
        ['user' => $user] = $this->resolveCrmApiContext($request);

        $contactId = $this->optionalContactId($request);

        $reminders = $contactId !== ''
            ? $this->reminders->pendingForContact($user, $contactId)
            : $this->reminders->pendingForDashboard($user);

        return ApiResponse::success([
            'reminders' => $reminders,
        ]);
    }

    /**
     * List contact reminders
     *
     * Pending reminders for one CRM contact. Tenant admins see every reminder on that contact.
     * Other users see reminders they set.
     *
     * @queryParam user_id int required Must match the authenticated user. Example: 1
     * @queryParam contactId string required CRM contact id. Also accepts `contact`, `contact_id`, or `contactid`. Example: 550e8400-e29b-41d4-a716-446655440000
     *
     * @response 200 {"success":true,"message":"OK","data":{"reminders":[]}}
     */
    public function forContact(Request $request): JsonResponse
    {
        ['user' => $user] = $this->resolveCrmApiContext($request);
        $contactId = $this->requiredContactId($request);

        return ApiResponse::success([
            'reminders' => $this->reminders->pendingForContact($user, $contactId),
        ]);
    }

    /**
     * Create a reminder
     *
     * When `noteId` is sent, the user's existing pending reminder for that note is updated instead of duplicated.
     * `reminder_at` is an ISO 8601 date-time. The offset is kept, so send the user's local time with its offset.
     *
     * @bodyParam user_id int required Must match the authenticated user. Example: 1
     * @bodyParam contactId string required CRM contact id. Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam reminder_at string required When to remind. Example: 2026-10-03T10:00:00+05:30
     * @bodyParam noteId string Optional CRM note id to attach this reminder to. Example: f47ac10b-58cc-4372-a567-0e02b2c3d479
     * @bodyParam body string Optional Note text stored as the reminder excerpt. Example: Call back about the quote
     * @bodyParam contact_name string Optional Display name. Local MyCrmSync contacts are named automatically when this is omitted. Example: Jane Doe
     *
     * @response 200 {"success":true,"message":"Reminder saved.","data":{"reminder":{"id":"6ba7b810-9dad-11d1-80b4-00c04fd430c8","contact_id":"550e8400-e29b-41d4-a716-446655440000","contact_note_id":null,"contact_name":"Jane Doe","note_excerpt":"Call back about the quote","remind_at":"2026-10-03T04:30:00+00:00","status":"pending","user_id":1,"user_name":"Alex","local_contact":true}}}
     */
    public function store(Request $request): JsonResponse
    {
        ['user' => $user] = $this->resolveCrmApiContext($request);

        $data = $request->validate([
            'contactId' => ['required_without_all:contact,contact_id,contactid', 'nullable', 'string', 'max:191'],
            'contact' => ['nullable', 'string', 'max:191'],
            'contact_id' => ['nullable', 'string', 'max:191'],
            'contactid' => ['nullable', 'string', 'max:191'],
            'reminder_at' => ['required', 'date'],
            'noteId' => ['nullable', 'string', 'max:191'],
            'noteid' => ['nullable', 'string', 'max:191'],
            'note_id' => ['nullable', 'string', 'max:191'],
            'body' => ['nullable', 'string'],
            'contact_name' => ['nullable', 'string', 'max:255'],
        ]);

        $contactId = $this->resolvedContactId($data);
        if ($contactId === '') {
            throw ValidationException::withMessages([
                'contactId' => ['The contactId field is required.'],
            ]);
        }

        $reminder = $this->reminders->create(
            $user,
            $contactId,
            $data['reminder_at'],
            $this->resolvedNoteId($data),
            (string) ($data['body'] ?? ''),
            $data['contact_name'] ?? null,
        );

        return ApiResponse::success([
            'reminder' => $this->reminders->payload($reminder),
        ], 'Reminder saved.');
    }

    /**
     * Update a reminder
     *
     * @bodyParam user_id int required Must match the authenticated user. Example: 1
     * @bodyParam reminderId string required Reminder id. Also accepts `reminder_id` or `reminderid`. Example: 6ba7b810-9dad-11d1-80b4-00c04fd430c8
     * @bodyParam reminder_at string required New reminder time. Example: 2026-10-04T09:00:00+05:30
     * @bodyParam body string Optional Replacement excerpt. Example: Updated follow-up
     * @bodyParam contact_name string Optional Replacement contact name. Example: Jane Doe
     * @bodyParam noteId string Optional Note id. Send an empty string to detach the note. Example: f47ac10b-58cc-4372-a567-0e02b2c3d479
     *
     * @response 200 {"success":true,"message":"Reminder updated.","data":{"reminder":{"id":"6ba7b810-9dad-11d1-80b4-00c04fd430c8","status":"pending"}}}
     */
    public function update(Request $request): JsonResponse
    {
        ['user' => $user] = $this->resolveCrmApiContext($request);
        $reminder = $this->authorizedReminder($request, $user);

        $data = $request->validate([
            'reminder_at' => ['required', 'date'],
            'body' => ['sometimes', 'nullable', 'string'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'noteId' => ['sometimes', 'nullable', 'string', 'max:191'],
            'noteid' => ['sometimes', 'nullable', 'string', 'max:191'],
            'note_id' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $noteId = null;
        if ($request->exists('noteId') || $request->exists('noteid') || $request->exists('note_id')) {
            $noteId = $this->resolvedNoteId($data);
        }

        $updated = $this->reminders->update(
            $reminder,
            $data['reminder_at'],
            $request->exists('body') ? (string) ($data['body'] ?? '') : null,
            $request->exists('contact_name') ? (string) ($data['contact_name'] ?? '') : null,
            $noteId,
        );

        return ApiResponse::success([
            'reminder' => $this->reminders->payload($updated),
        ], 'Reminder updated.');
    }

    /**
     * Complete a reminder
     *
     * @bodyParam user_id int required Must match the authenticated user. Example: 1
     * @bodyParam reminderId string required Reminder id. Example: 6ba7b810-9dad-11d1-80b4-00c04fd430c8
     *
     * @response 200 {"success":true,"message":"Reminder completed.","data":{"reminder":{"id":"6ba7b810-9dad-11d1-80b4-00c04fd430c8","status":"completed"}}}
     */
    public function complete(Request $request): JsonResponse
    {
        ['user' => $user] = $this->resolveCrmApiContext($request);
        $reminder = $this->authorizedReminder($request, $user);
        $updated = $this->reminders->complete($reminder);

        return ApiResponse::success([
            'reminder' => $this->reminders->payload($updated),
        ], 'Reminder completed.');
    }

    /**
     * Dismiss a reminder
     *
     * @bodyParam user_id int required Must match the authenticated user. Example: 1
     * @bodyParam reminderId string required Reminder id. Example: 6ba7b810-9dad-11d1-80b4-00c04fd430c8
     *
     * @response 200 {"success":true,"message":"Reminder dismissed.","data":{"reminder":{"id":"6ba7b810-9dad-11d1-80b4-00c04fd430c8","status":"dismissed"}}}
     */
    public function dismiss(Request $request): JsonResponse
    {
        ['user' => $user] = $this->resolveCrmApiContext($request);
        $reminder = $this->authorizedReminder($request, $user);
        $updated = $this->reminders->dismiss($reminder);

        return ApiResponse::success([
            'reminder' => $this->reminders->payload($updated),
        ], 'Reminder dismissed.');
    }

    /**
     * Snooze a reminder
     *
     * Send either `minutes` from now, or an absolute `reminder_at`.
     *
     * @bodyParam user_id int required Must match the authenticated user. Example: 1
     * @bodyParam reminderId string required Reminder id. Example: 6ba7b810-9dad-11d1-80b4-00c04fd430c8
     * @bodyParam minutes int Minutes from now. Example: 60
     * @bodyParam reminder_at string Absolute time. Example: 2026-10-04T09:00:00+05:30
     *
     * @response 200 {"success":true,"message":"Reminder snoozed.","data":{"reminder":{"id":"6ba7b810-9dad-11d1-80b4-00c04fd430c8","status":"pending"}}}
     */
    public function snooze(Request $request): JsonResponse
    {
        ['user' => $user] = $this->resolveCrmApiContext($request);
        $reminder = $this->authorizedReminder($request, $user);

        $data = $request->validate([
            'minutes' => ['required_without:reminder_at', 'nullable', 'integer', 'min:1', 'max:43200'],
            'reminder_at' => ['required_without:minutes', 'nullable', 'date', 'after:now'],
        ]);

        if (empty($data['reminder_at']) && empty($data['minutes'])) {
            throw ValidationException::withMessages([
                'reminder_at' => ['A reminder time or a number of minutes is required.'],
            ]);
        }

        $remindAt = ! empty($data['reminder_at'])
            ? Carbon::parse($data['reminder_at'])
            : now()->addMinutes((int) $data['minutes']);

        $updated = $this->reminders->snooze($reminder, $remindAt);

        return ApiResponse::success([
            'reminder' => $this->reminders->payload($updated),
        ], 'Reminder snoozed.');
    }

    private function authorizedReminder(Request $request, User $user): ContactReminder
    {
        $reminder = $this->reminders->findForUser($user, $this->requiredReminderId($request));
        $this->reminders->assertCanManage($user, $reminder);

        return $reminder;
    }

    private function requiredReminderId(Request $request): string
    {
        $data = $request->validate([
            'reminderId' => ['nullable', 'uuid'],
            'reminder_id' => ['nullable', 'uuid'],
            'reminderid' => ['nullable', 'uuid'],
        ]);

        $id = trim((string) ($data['reminderId'] ?? $data['reminder_id'] ?? $data['reminderid'] ?? ''));

        if ($id === '') {
            throw ValidationException::withMessages([
                'reminderId' => ['The reminderId field is required.'],
            ]);
        }

        return $id;
    }

    private function optionalContactId(Request $request): string
    {
        $data = $request->validate([
            'contactId' => ['nullable', 'string', 'max:191'],
            'contact' => ['nullable', 'string', 'max:191'],
            'contact_id' => ['nullable', 'string', 'max:191'],
            'contactid' => ['nullable', 'string', 'max:191'],
        ]);

        return $this->resolvedContactId($data);
    }

    private function requiredContactId(Request $request): string
    {
        $contactId = $this->optionalContactId($request);

        if ($contactId === '') {
            throw ValidationException::withMessages([
                'contactId' => ['The contactId field is required.'],
            ]);
        }

        return $contactId;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvedContactId(array $data): string
    {
        return trim((string) (
            $data['contactId']
                ?? $data['contact']
                ?? $data['contact_id']
                ?? $data['contactid']
                ?? ''
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvedNoteId(array $data): string
    {
        return trim((string) (
            $data['noteId']
                ?? $data['noteid']
                ?? $data['note_id']
                ?? ''
        ));
    }
}
