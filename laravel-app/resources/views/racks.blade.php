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

    <!-- Footer -->
    <footer class="bg-white border-t-2 border-black py-8">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center">
            <div class="mb-4 md:mb-0">
                <p class="text-gray-700">
                    Made with ❤️ for the Ableton community by 
                    <a href="https://bass-daddy.com" target="_blank" rel="noopener noreferrer" class="link">
                        Bass Daddy
                    </a>
                </p>
            </div>
            <div class="flex items-center space-x-6">
                <a href="https://github.com/teamallnighter/ableton-cookbook.git" target="_blank" rel="noopener noreferrer" class="link flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                    </svg>
                    <span>GitHub</span>
                </a>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>