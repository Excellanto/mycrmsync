<?php

namespace App\Http\Controllers;

use App\Services\ShortUrl\ShortUrlService;
use Illuminate\Http\RedirectResponse;

final class ShortUrlRedirectController extends Controller
{
    public function __construct(private ShortUrlService $shortUrls) {}

    public function show(string $code): RedirectResponse
    {
        $shortUrl = $this->shortUrls->findByCode($code);

        if ($shortUrl === null) {
            abort(404, 'Short URL not found.');
        }

        return redirect()->away($shortUrl->long_url, 302);
    }
}
