<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="{{ ($colorScheme ?? 'dark') === 'dark' ? 'dark' : '' }}"
      data-color-scheme="{{ $colorScheme ?? 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ config('app.name') }}</title>

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
