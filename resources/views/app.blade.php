<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="{{ ($colorScheme ?? 'dark') === 'dark' ? 'dark' : '' }}"
      data-color-scheme="{{ $colorScheme ?? 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600|jetbrains-mono:400,500" rel="stylesheet">

    {{-- Inlined so the accent paints before Vue boots. --}}
    <style>{!! $accentCss ?? '' !!}</style>
    <script>
        (function () {
            var scheme = document.documentElement.dataset.colorScheme;
            if (scheme === 'system') {
                document.documentElement.classList.toggle(
                    'dark',
                    window.matchMedia('(prefers-color-scheme: dark)').matches
                );
            }
        })();
    </script>

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
