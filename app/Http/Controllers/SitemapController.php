<?php

namespace App\Http\Controllers;

use App\Models\Cause;
use App\Support\DonationPublicFrontend;
use App\Support\Seo;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = [
            [
                'loc' => DonationPublicFrontend::homeUrl(),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ],
            [
                'loc' => Seo::canonicalUrl(route('donate.bank-details', [], false)),
                'lastmod' => now()->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ],
        ];

        Cause::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['slug', 'updated_at'])
            ->each(function (Cause $cause) use (&$urls): void {
                $urls[] = [
                    'loc' => DonationPublicFrontend::donateCauseUrl($cause->slug),
                    'lastmod' => ($cause->updated_at ?? now())->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.8',
                ];
            });

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'text/xml; charset=UTF-8');
    }
}
