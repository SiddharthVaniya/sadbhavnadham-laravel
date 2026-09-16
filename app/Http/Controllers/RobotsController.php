<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $sitemap = Seo::canonicalUrl('/sitemap.xml');

        $content = implode("\n", [
            'User-agent: *',
            'Disallow: /admin/',
            'Disallow: /api/',
            'Disallow: /receipts/demo',
            'Disallow: /google-auth',
            'Disallow: /oauth2callback',
            '',
            'Sitemap: '.$sitemap,
        ]);

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
