<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $sitemaps = [
            ['loc' => route('sitemap.static'), 'lastmod' => now()->toISOString()],
            ['loc' => route('sitemap.racks'), 'lastmod' => $this->getLastRackUpdate()],
            ['loc' => route('sitemap.users'), 'lastmod' => $this->getLastUserUpdate()],
        ];

        return response()
            ->view('sitemaps.index', compact('sitemaps'))
            ->header('Content-Type', 'application/xml');
    }

    public function static(): Response
    {
        $staticPages = [
            [
                'loc' => route('home'),
                'lastmod' => now()->toISOString(),
                'changefreq' => 'daily',
                'priority' => '1.0'
            ],
            [
                'loc' => route('racks.upload'),
                'lastmod' => now()->toISOString(),
                'changefreq' => 'monthly',
                'priority' => '0.7'
            ],
        ];

        return response()
            ->view('sitemaps.urlset', ['urls' => $staticPages])
            ->header('Content-Type', 'application/xml');
    }

    public function racks(): Response
    {
        $racks = Rack::published()
            ->select(['id', 'slug', 'title', 'updated_at', 'published_at'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($rack) {
                return [
                    'loc' => route('racks.show', $rack),
                    'lastmod' => $rack->updated_at->toISOString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                    'image' => $rack->preview_image_path ? [
                        'loc' => asset('storage/' . $rack->preview_image_path),
                        'title' => $rack->title,
                        'caption' => $rack->title . ' - Ableton Live Rack Preview',
                    ] : null,
                ];
            });

        return response()
            ->view('sitemaps.urlset', ['urls' => $racks])
            ->header('Content-Type', 'application/xml');
    }

    public function users(): Response
    {
        $users = User::whereHas('racks', function ($query) {
                $query->published();
            })
            ->select(['id', 'name', 'updated_at'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($user) {
                return [
                    'loc' => route('users.show', $user),
                    'lastmod' => $user->updated_at->toISOString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.6',
                ];
            });

        return response()
            ->view('sitemaps.urlset', ['urls' => $users])
            ->header('Content-Type', 'application/xml');
    }

    private function getLastRackUpdate(): string
    {
        $lastRack = Rack::published()->latest('updated_at')->first();
        return $lastRack ? $lastRack->updated_at->toISOString() : now()->toISOString();
    }

    private function getLastUserUpdate(): string
    {
        $lastUser = User::whereHas('racks', function ($query) {
            $query->published();
        })->latest('updated_at')->first();
        
        return $lastUser ? $lastUser->updated_at->toISOString() : now()->toISOString();
    }
}