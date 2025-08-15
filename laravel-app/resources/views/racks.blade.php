<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO Meta Tags --}}
    <x-seo-meta :metaTags="app('App\Services\SeoService')->getHomeMetaTags()" />

    {{-- Structured Data --}}
    <x-structured-data :data="app('App\Services\SeoService')->getStructuredData('website')" />

    {{-- Favicon and App Icons --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/teamallnighter/abletonSans@latest/abletonSans.css">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-100" itemscope itemtype="https://schema.org/WebPage">
    <!-- Navigation -->
    @auth
        @livewire('navigation-menu')
    @else
        <nav class="bg-white border-b-2 border-black">
            <div class="max-w-7xl mx-auto px-6">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="{{ route('home') }}" class="flex items-center space-x-3 hover:opacity-90 transition-opacity" aria-label="Ableton Cookbook - Home">
                            <div class="w-8 h-8 bg-vibrant-purple rounded flex items-center justify-center">
                                <span class="text-white font-bold text-sm" aria-hidden="true">AC</span>
                            </div>
                            <span class="text-black font-bold hidden sm:block">Ableton Cookbook</span>
                        </a>
                    </div>

                    <!-- Guest Auth Links -->
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('login') }}" class="link">
                            Login
                        </a>
                        <a href="{{ route('register') }}" class="btn-primary">
                            Register
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    @endauth

    <!-- Main Content -->
    <main class="min-h-screen bg-gray-100" role="main">
        <div class="sr-only">
            <h1>Ableton Cookbook - Share and Discover Ableton Live Racks</h1>
            <p>Browse and download high-quality Ableton Live racks including instrument racks, audio effect racks, and MIDI racks shared by music producers worldwide.</p>
        </div>
        @livewire('rack-browser')
    </main>

    @livewireScripts
</body>
</html>