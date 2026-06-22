<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>MySimConnect API — {{ config('app.name') }}</title>

    <style>

        :root { color-scheme: light dark; --fg: #111; --muted: #555; --border: #ddd; --code: #f4f4f5; }

        @media (prefers-color-scheme: dark) {

            :root { --fg: #f4f4f5; --muted: #a1a1aa; --border: #3f3f46; --code: #27272a; }

        }

        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.55; color: var(--fg); max-width: 880px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }

        h1 { font-size: 1.75rem; margin-bottom: 0.25rem; }

        .sub { color: var(--muted); margin-bottom: 2rem; }

        h2 { font-size: 1.2rem; margin-top: 2rem; border-bottom: 1px solid var(--border); padding-bottom: 0.35rem; }

        code, pre { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.85rem; }

        pre { background: var(--code); padding: 1rem; overflow-x: auto; border-radius: 8px; border: 1px solid var(--border); }

        ul { padding-left: 1.2rem; }

        li { margin: 0.35rem 0; }

        a { color: inherit; }

    </style>

</head>

<body>

    <h1>MySimConnect API</h1>

    <p class="sub">Base URL for JSON routes: <code>{{ url('/api') }}</code>. Many admin features use the web app under <code>{{ url('/admin') }}</code> instead of this API.</p>



    <h2>Scribe (interactive docs + exports)</h2>

    <ul>

        <li><strong>HTML documentation (Try it out):</strong> <a href="{{ url('/docs') }}">{{ url('/docs') }}</a></li>

        <li><strong>Postman collection (v2.1 JSON):</strong> <a href="{{ url('/docs.postman') }}">{{ url('/docs.postman') }}</a></li>

        <li><strong>OpenAPI YAML:</strong> <a href="{{ url('/docs.openapi') }}">{{ url('/docs.openapi') }}</a></li>

    </ul>

    <p class="sub">After changing routes or docblocks under <code>app/Http/Controllers/Api</code>, regenerate with <code>php artisan scribe:generate</code> or <code>composer docs</code>. Optionally set <code>SCRIBE_AUTH_KEY</code> in <code>.env</code> for authenticated &quot;Try it out&quot; requests.</p>



    <h2>Overview</h2>

    <ul>

        <li>Documented JSON responses often use: <code>{ "success": true|false, "message": "...", "data": ... }</code>.</li>

        <li>Protected API routes (when enabled) expect <code>Authorization: Bearer &lt;token&gt;</code> (see Scribe authentication notes).</li>

    </ul>



    <p class="sub">Generated for {{ config('app.name') }} · {{ now()->toDateString() }}</p>

</body>

</html>

