<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('images/favicon.ico') }}" type="image/x-icon">
    <title>{{ config('app.name', 'DartManager') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gradient-to-br from-gray-950 via-gray-900 to-gray-950 text-gray-200 font-sans antialiased">

    <div class="min-h-screen">
        @include('layouts.navigation')

        @isset($header)
        <header class="max-w-full mx-auto py-6 px-6">
            {{ $header }}
        </header>
        @endisset

        <main class="max-w-full mx-auto px-6 pb-12">
            {{ $slot }}
        </main>
    </div>

    @stack('scripts')
</body>

</html>