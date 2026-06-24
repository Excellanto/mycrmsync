<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Services\Email\EmailTemplateRenderer;
use App\Services\Email\EmailTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class EmailTemplateController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', EmailTemplate::class);

        $templates = EmailTemplate::query()
            ->orderBy('name')
            ->get();

        $selectedSlug = $request->string('template')->toString();
        if ($selectedSlug === '' || ! $templates->contains('slug', $selectedSlug)) {
            $selectedSlug = (string) ($templates->first()?->slug ?? EmailTemplate::SLUG_LOGIN_OTP);
        }

        return Inertia::render('Admin/EmailTemplates/Index', [
            'templates' => $templates,
            'selectedSlug' => $selectedSlug,
            'sampleVariables' => $this->sampleVariables(),
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->authorize('update', $emailTemplate);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'html_body' => ['required', 'string', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ]);

        $emailTemplate->update($data);

        EmailTemplateService::forget($emailTemplate->slug);

        return redirect()
            ->route('admin.email-templates.index', ['template' => $emailTemplate->slug])
            ->with('success', 'Email template saved.');
    }

    public function preview(Request $request, EmailTemplate $emailTemplate): Response
    {
        $this->authorize('viewAny', EmailTemplate::class);

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'html_body' => ['nullable', 'string', 'max:65535'],
        ]);

        $subject = $data['subject'] ?? $emailTemplate->subject;
        $htmlBody = $data['html_body'] ?? $emailTemplate->html_body;

        $rendered = EmailTemplateRenderer::render(
            $subject,
            $htmlBody,
            $this->sampleVariables()[$emailTemplate->slug] ?? [],
        );

        return response($rendered['html'], 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function sampleVariables(): array
    {
        $appName = (string) config('app.name');
        $sampleResetUrl = url('/reset-password/sample-token?email=user@example.com');

        return [
            EmailTemplate::SLUG_LOGIN_OTP => [
                'otp_code' => '1234',
                'user_name' => 'Sample User',
                'user_email' => 'user@example.com',
                'app_name' => $appName,
                'expires_minutes' => '10',
            ],
            EmailTemplate::SLUG_PASSWORD_RECOVERY => [
                'user_name' => 'Sample User',
                'user_email' => 'user@example.com',
                'reset_url' => $sampleResetUrl,
                'app_name' => $appName,
                'expires_minutes' => (string) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
            ],
        ];
    }
}
