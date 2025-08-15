<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Ableton Cookbook') }}</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/teamallnighter/abletonSans@latest/abletonSans.css">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased bg-gray-100">
    <!-- Navigation -->
    @auth
        @livewire('navigation-menu')
    @else
        <nav class="bg-white border-b-2 border-black">
            <div class="max-w-7xl mx-auto px-6">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="{{ route('home') }}" class="flex items-center space-x-3 hover:opacity-90 transition-opacity">
                            <div class="w-8 h-8 bg-vibrant-purple rounded flex items-center justify-center">
                                <span class="text-white font-bold text-sm">AC</span>
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
    <main class="min-h-screen bg-gray-100">
        @livewire('rack-browser')
    </main>

    @livewireScripts
</body>
</html>