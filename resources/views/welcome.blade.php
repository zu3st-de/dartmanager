<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-950 text-gray-200 min-h-screen flex items-center justify-center">

    <div class="text-center">

        <!-- Logo -->
        <div class="flex justify-center mb-8">
            <img src="{{ asset('images/logo.png') }}"
                class="h-80 w-auto"
                alt="Dart-Manager">
        </div>

        <!-- Buttons -->
        <div class="flex justify-center gap-4">

            @auth
            <a href="{{ route('tournaments.index') }}"
                class="px-6 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-white transition">
                Zu den Turnieren
            </a>
            @else
            <a href="{{ route('login') }}"
                class="px-6 py-2 border border-gray-700 hover:bg-gray-800 rounded-lg transition">
                Log in
            </a>

            <a href="{{ route('register') }}"
                class="px-6 py-2 bg-emerald-600 hover:bg-emerald-500 rounded-lg text-white transition">
                Registrieren
            </a>
            @endauth

        </div>

    </div>

</body>

</html>