@props(['title' => null])
@php
    $pageTitle = $title ?? \Illuminate\Support\Str::of(request()->route()?->getName() ?? '')
        ->before('.')
        ->replace(['-', '_'], ' ')
        ->title()
        ->value();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['h-full', 'dark' => request()->cookie('theme') === 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle ? $pageTitle . ' · ' : '' }}SocialM</title>
    <link rel="icon" type="image/png" href="{{ asset('farm_assets/images/farm_images/mini-logo1.png') }}">

    {{-- Set the theme before the first paint so dark mode doesn't flash. The cookie lets
         the server render the same class on later pages (see resources/js/app.js). --}}
    <script>
        (function () {
            try {
                var mode = localStorage.getItem('theme');
                var dark = mode === 'dark' || (!mode && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
                document.cookie = 'theme=' + (dark ? 'dark' : 'light') + ';path=/;max-age=31536000;SameSite=Lax';
            } catch (e) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full">
    {{ $slot }}

    <x-ui.toasts />
    <x-ui.confirm />

    @livewireScripts
</body>
</html>
