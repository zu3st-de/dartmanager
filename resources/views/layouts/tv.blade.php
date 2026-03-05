<!DOCTYPE html>
<html lang="de" class="dark">

<head>

    <meta charset="utf-8">
    <title>{{ $tournament->name }} – TV</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    @vite(['resources/css/app.css','resources/js/app.js'])

</head>

<body class="bg-gray-950 text-gray-200 min-h-screen">

    <div class="p-10">

        @yield('content')

    </div>

    <script>
        setInterval(() => {
            window.location.reload();
        }, 15000);
    </script>

</body>

</html>