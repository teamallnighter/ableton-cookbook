<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO Meta Tags --}}
    <x-seo-meta :metaTags="app('App\Services\SeoService')->getRackMetaTags($rack)" />

    {{-- Structured Data --}}
    <x-structured-data :data="app('App\Services\SeoService')->getStructuredData('rack', ['rack' => $rack])" />

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
<body class="font-sans antialiased" style="background-color: #C3C3C3;" itemscope itemtype="https://schema.org/ItemPage">
    <!-- Navigation -->
    <nav class="shadow-sm border-b-2" style="background-color: #0D0D0D; border-color: #01CADA;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center" aria-label="Ableton Cookbook - Home">
                        <span class="text-xl font-bold" style="color: #ffdf00;">🎵 Ableton Cookbook</span>
                    </a>
                </div>

                <!-- Auth Links -->
                <div class="flex items-center space-x-4">
                    @auth
                        <a href="{{ route('dashboard') }}" style="color: #BBBBBB;" class="hover:text-opacity-80 transition-colors">
                            Dashboard
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" style="color: #BBBBBB;" class="hover:text-opacity-80 transition-colors">
                                Logout
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" style="color: #BBBBBB;" class="hover:text-opacity-80 transition-colors">
                            Login
                        </a>
                        <a href="{{ route('register') }}" style="background-color: #01CADA; color: #0D0D0D;" class="px-4 py-2 rounded-lg hover:opacity-90 transition-opacity font-semibold">
                            Register
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main role="main">
        {{-- Breadcrumbs --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <x-breadcrumbs :items="[
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Racks', 'url' => route('home')],
                ['name' => $rack->title, 'url' => route('racks.show', $rack)]
            ]" />
        </div>

        {{-- Hidden structured content for SEO --}}
        <div class="sr-only">
            <h1>{{ $rack->title }} - {{ ucfirst($rack->rack_type) }} Rack for Ableton Live</h1>
            <p>Download {{ $rack->title }}, a high-quality {{ $rack->rack_type }} rack for Ableton Live. Created by {{ $rack->user->name }}.</p>
        </div>

        @livewire('rack-show', ['rack' => $rack])
    </main>

    @livewireScripts
</body>
</html>