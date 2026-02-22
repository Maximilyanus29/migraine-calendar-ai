<!doctype html>
<html lang="ru">
@php
    $appJsAsset = Vite::asset('resources/js/app.js');
    preg_match('/app-([A-Za-z0-9_-]+)\.js$/', $appJsAsset, $matches);
    $buildHash = $matches[1] ?? 'dev';
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1c7a87">
    <meta name="app-build-hash" content="{{ $buildHash }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/icon-192.svg">
    <title>Migraine AI</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
