<?php

use App\Http\Controllers\Api\Auth\EmailOtpAuthController;
use App\Http\Controllers\Api\Crm\CallLogController;
use App\Http\Controllers\Api\Crm\CallRecordingController;
use App\Http\Controllers\Api\Crm\ContactController;
use App\Http\Controllers\Api\Crm\ContactFileController;
use App\Http\Controllers\Api\Crm\ContactNoteSummaryController;
use App\Http\Controllers\Api\Crm\ContactMediaBatchController;
use App\Http\Controllers\Api\Crm\VoiceNoteController;
use App\Http\Controllers\Api\Integrations\GoHighLevel\GhlCompatController;
use Illuminate\Support\Facades\Route;

Route::post('auth/email-otp/send', [EmailOtpAuthController::class, 'send'])
    ->middleware('throttle:10,1')
    ->name('api.auth.email-otp.send');
Route::post('auth/email-otp/verify', [EmailOtpAuthController::class, 'verify'])
    ->middleware('throttle:30,1')
    ->name('api.auth.email-otp.verify');

Route::post('auth/logout', [EmailOtpAuthController::class, 'logout'])
    ->middleware(['auth:sanctum', 'throttle:30,1'])
    ->name('api.auth.logout');

/*
|--------------------------------------------------------------------------
| Call log register (background mobile sync — no authentication)
|--------------------------------------------------------------------------
| Only `user_id` is required (must exist and belong to a tenant).
*/
Route::prefix('crm/call-logs')
    ->middleware(['throttle:120,1'])
    ->group(function () {
        Route::post('/get', [CallLogController::class, 'getCallLogs'])->name('api.crm.call-logs.get');
        Route::post('/register/bulk', [CallLogController::class, 'registerBulk'])->name('api.crm.call-logs.register.bulk');
        Route::post('/register', [CallLogController::class, 'register'])->name('api.crm.call-logs.register');
    });

/*
|--------------------------------------------------------------------------
| CRM compatibility endpoints (Lead Connector / GoHighLevel parity)
|--------------------------------------------------------------------------
| Paths mirror https://services.leadconnectorhq.com/ (under /api/crm).
| Requires `Authorization: Bearer <token>` from email OTP verify.
| Send `user_id` matching the authenticated user so the controller can
| resolve the tenant's saved CRM credentials.
|
| Register `contacts/search` before `contacts/{contactId}/...` so "search"
| is not treated as a contact id. `tags` is shortened from GHL's
| `/locations/{locationId}/tags` (location id is resolved from tenant config).
*/
Route::prefix('crm')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->group(function () {
        Route::get('/call-logs', [CallLogController::class, 'index'])->name('api.crm.call-logs.index');

        Route::get('/contacts', [GhlCompatController::class, 'listContacts'])->name('api.crm.contacts.index');
        Route::get('/contact', [ContactController::class, 'show'])->name('api.crm.contact.show');
        Route::post('/contacts/search', [GhlCompatController::class, 'searchContacts'])->name('api.crm.contacts.search');
        Route::post('/contacts/add', [GhlCompatController::class, 'createContact'])->name('api.crm.contacts.store');
        Route::post('/contacts/delete', [GhlCompatController::class, 'deleteContact'])->name('api.crm.contacts.destroy');
        Route::get('/users', [GhlCompatController::class, 'listUsers'])->name('api.crm.users.index');
        Route::get('/tags', [GhlCompatController::class, 'listLocationTags'])->name('api.crm.tags.index');

        Route::post('/contacts/add/tags', [GhlCompatController::class, 'addContactTags'])->name('api.crm.contacts.tags.store');

        Route::get('/contacts/notes/list', [GhlCompatController::class, 'listContactNotes'])->name('api.crm.contacts.notes.index');
        Route::get('/contacts/notes/summaries-by-phone', [ContactNoteSummaryController::class, 'byPhone'])->name('api.crm.contacts.notes.summaries-by-phone');
        Route::post('/contacts/notes/add', [GhlCompatController::class, 'createContactNote'])->name('api.crm.contacts.notes.store');
        Route::post('/contacts/notes/update', [GhlCompatController::class, 'updateContactNote'])->name('api.crm.contacts.notes.update');
        Route::post('/contacts/notes/delete', [GhlCompatController::class, 'deleteContactNote'])->name('api.crm.contacts.notes.destroy');

        Route::post('/voice-notes/process', [VoiceNoteController::class, 'process'])->name('api.crm.voice-notes.process');
        Route::post('/call-recordings/transcribe', [CallRecordingController::class, 'transcribe'])->name('api.crm.call-recordings.transcribe');
        Route::get('/call-recordings/{callRecordingId}', [CallRecordingController::class, 'show'])->name('api.crm.call-recordings.show');
        Route::post('/contact-files/process', [ContactFileController::class, 'process'])->name('api.crm.contact-files.process');
        Route::post('/media/process-batch', [ContactMediaBatchController::class, 'process'])->name('api.crm.media.process-batch');
    });
