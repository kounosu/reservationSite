<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Reservation Site') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/css/pages/reservations/index.css', 'resources/js/app.js'])
    </head>
    <body class="reservation-page">
        <div id="reservation-app" class="reservation-app-shell">
            <div class="reservation-app-loading">カレンダーを読み込み中...</div>
        </div>

        <script id="reservation-config" type="application/json">{!! json_encode($frontendConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    </body>
</html>
