<!DOCTYPE html>
<html lang="de" class="dark">

<head>

    <meta charset="utf-8">
    <link rel="icon" href="{{ asset('images/favicon.ico') }}" type="image/x-icon">
    <link rel="shortcut icon" href="{{ asset('images/favicon.ico') }}" type="image/x-icon">
    <title>{{ $tournament->name ?? 'Turnier TV' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @vite(['resources/css/app.css','resources/js/app.js'])

    @stack('styles')

</head>

<body class="bg-gray-950 text-gray-200 min-h-screen overflow-hidden">

    <div class="p-10">

        @yield('content')

    </div>

    @stack('scripts')

</body>

</html>
