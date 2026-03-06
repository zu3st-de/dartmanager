<!DOCTYPE html>
<html lang="de">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $tournament->name ?? 'Dart Turnier' }}</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Bootstrap (optional) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Eigenes CSS -->
    <link rel="stylesheet" href="{{ asset('css/follow.css') }}">

    <style>
        body {
            background: #0f0f0f;
            color: white;
            font-family: Arial, sans-serif;
        }
    </style>

</head>

<body>

    <div class="container py-3">

        @yield('content')

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    @stack('scripts')

</body>

</html>