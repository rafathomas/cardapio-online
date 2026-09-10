<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Establishment;
use Illuminate\Http\Response;

/**
 * Sitemap e robots.txt gerados dinamicamente.
 * Apenas cardapios publicados E marcados como indexaveis entram no sitemap.
 */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $establishments = Establishment::query()
            ->where('is_published', true)
            ->where('is_indexable', true)
            ->orderBy('id')
            ->get(['slug', 'updated_at']);

        $xml = view('public.sitemap', [
            'establishments' => $establishments,
        ])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /app',
            'Disallow: /admin',
            'Disallow: /api',
            'Disallow: /webhooks',
            'Allow: /',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
