@php
    ['palette' => $palette, 'dark' => $dark] = \App\Support\Theme\Palette::forErrorPage();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="{{ $dark instanceof \App\Support\Theme\Palette ? 'light dark' : $palette->scheme }}">
    <title>@yield('title') — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600|jetbrains-mono:500,600" rel="stylesheet">

    <style>
        :root { {!! $palette->cssVariables() !!} }
        @if ($dark instanceof \App\Support\Theme\Palette)
        @media (prefers-color-scheme: dark) {
            :root { {!! $dark->cssVariables() !!} }
        }
        @endif

        *, *::before, *::after { box-sizing: border-box; }

        body {
            align-items: center;
            background-color: var(--mb-canvas);
            color: var(--mb-text);
            display: flex;
            font-family: {!! \App\Support\Theme\Palette::FONT_SANS !!};
            -webkit-font-smoothing: antialiased;
            justify-content: center;
            line-height: 1.6;
            margin: 0;
            min-height: 100vh;
            padding: 24px;
        }

        .card {
            background-color: var(--mb-surface);
            border: 1px solid var(--mb-border);
            border-radius: 6px;
            max-width: 480px;
            padding: 40px;
            width: 100%;
        }

        .code {
            color: var(--mb-text-muted);
            display: block;
            font-family: {!! \App\Support\Theme\Palette::FONT_MONO !!};
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.06em;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        h1 {
            font-size: 20px;
            font-weight: 600;
            letter-spacing: -0.01em;
            margin: 0 0 12px;
        }

        p {
            color: var(--mb-text-secondary);
            font-size: 15px;
            margin: 0;
        }

        .back {
            border-top: 1px solid var(--mb-border-subtle);
            display: block;
            margin-top: 28px;
            padding-top: 20px;
        }

        .back a {
            color: var(--mb-accent);
            font-family: {!! \App\Support\Theme\Palette::FONT_MONO !!};
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-decoration: none;
            text-transform: uppercase;
        }

        .back a:hover { text-decoration: underline; }

        @media (max-width: 480px) {
            .card { border-left-width: 0; border-radius: 0; border-right-width: 0; padding: 32px 20px; }
        }
    </style>
</head>
<body>
    <main class="card" role="main">
        <span class="code" lang="en">@yield('code') · @yield('title')</span>

        <h1>@yield('heading')</h1>

        <p>@yield('message')</p>

        <span class="back"><a href="{{ url('/') }}" lang="en">{{ __('Back to the panel') }}</a></span>
    </main>
</body>
</html>
