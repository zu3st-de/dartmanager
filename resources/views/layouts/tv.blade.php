<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>TV – {{ $tournament->name ?? 'Turnier' }}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    @vite(['resources/css/app.css','resources/js/app.js'])
</head>

<body class="bg-black text-white min-h-screen">

    <div class="p-8">

        @yield('content')

    </div>

    <script>
        setInterval(() => location.reload(), 15000);
    </script>

</body>

</html>