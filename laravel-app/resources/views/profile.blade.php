<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $user->name }} - {{ config('app.name', 'Ableton Cookbook') }}</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/teamallnighter/abletonSans@latest/abletonSans.css">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased" style="background-color: #C3C3C3;">
    <!-- Navigation -->
    <nav class="shadow-sm border-b-2" style="background-color: #0D0D0D; border-color: #01CADA;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center">
                        <span class="text-xl font-bold" style="color: #ffdf00;">🎵 Ableton Cookbook</span>
                    </a>
                </div>

                <!-- Auth Links -->
                <div class="flex items-center space-x-4">
                    @auth
                        <a href="{{ route('racks.upload') }}" style="background-color: #01DA48; color: #0D0D0D;" class="px-4 py-2 rounded-lg hover:opacity-90 transition-opacity font-semibold">
                            Upload Rack
                        </a>
                        <a href="{{ route('profile') }}" style="color: #BBBBBB;" class="hover:text-opacity-80 transition-colors">
                            My Profile
                        </a>
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
    <main>
        @livewire('user-profile', ['user' => $user])
    </main>

    @livewireScripts
</body>
</html>