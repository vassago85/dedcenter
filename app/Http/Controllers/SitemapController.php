<?php

namespace App\Http\Controllers;

use App\Enums\MatchStatus;
use App\Models\Organization;
use App\Models\ShootingMatch;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect();

        $staticPages = [
            ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => url('/advertise'), 'priority' => '0.75', 'changefreq' => 'monthly'],
            ['loc' => url('/features'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => url('/scoring'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => url('/offline'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/setup'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => url('/login'), 'priority' => '0.5', 'changefreq' => 'yearly'],
            ['loc' => url('/register'), 'priority' => '0.5', 'changefreq' => 'yearly'],
        ];

        foreach ($staticPages as $page) {
            $urls->push($page);
        }

        $matches = ShootingMatch::whereIn('status', [MatchStatus::Active, MatchStatus::Completed])
            ->orderByDesc('date')
            ->get();

        foreach ($matches as $match) {
            $urls->push([
                'loc' => route('scoreboard', $match),
                'lastmod' => ($match->updated_at ?? $match->created_at)?->toW3cString(),
                'priority' => $match->status === MatchStatus::Active ? '0.9' : '0.6',
                'changefreq' => $match->status === MatchStatus::Active ? 'hourly' : 'monthly',
            ]);
            $urls->push([
                'loc' => route('live', $match),
                'lastmod' => ($match->updated_at ?? $match->created_at)?->toW3cString(),
                'priority' => $match->status === MatchStatus::Active ? '0.9' : '0.5',
                'changefreq' => $match->status === MatchStatus::Active ? 'always' : 'monthly',
            ]);
        }

        $orgs = Organization::all();
        foreach ($orgs as $org) {
            $urls->push([
                'loc' => route('portal.home', $org),
                'priority' => '0.7',
                'changefreq' => 'weekly',
            ]);
            $urls->push([
                'loc' => route('portal.matches', $org),
                'priority' => '0.7',
                'changefreq' => 'weekly',
            ]);
            $urls->push([
                'loc' => route('portal.leaderboard', $org),
                'priority' => '0.6',
                'changefreq' => 'weekly',
            ]);
            $urls->push([
                'loc' => route('leaderboard', $org),
                'priority' => '0.6',
                'changefreq' => 'weekly',
            ]);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($url['loc'])."</loc>\n";
            if (! empty($url['lastmod'])) {
                $xml .= "    <lastmod>{$url['lastmod']}</lastmod>\n";
            }
            $xml .= "    <changefreq>{$url['changefreq']}</changefreq>\n";
            $xml .= "    <priority>{$url['priority']}</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
