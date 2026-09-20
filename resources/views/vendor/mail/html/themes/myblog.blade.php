@php
    $palette = ($palette ?? null) instanceof \App\Support\Theme\Palette
        ? $palette
        : \App\Support\Theme\Palette::forUser(null);
    $sans = \App\Support\Theme\Palette::FONT_SANS;
    $mono = \App\Support\Theme\Palette::FONT_MONO;
@endphp

body {
    background-color: {{ $palette->canvas }};
    color: {{ $palette->text }};
    font-family: {!! $sans !!};
    -webkit-text-size-adjust: none;
    margin: 0;
    padding: 0;
    width: 100% !important;
}

.wrapper {
    background-color: {{ $palette->canvas }};
    margin: 0;
    padding: 0;
    width: 100%;
}

.content {
    margin: 0;
    padding: 0;
    width: 100%;
}

.header {
    padding: 32px 0 20px;
    text-align: center;
}

.header a {
    color: {{ $palette->textSecondary }};
    font-family: {!! $mono !!};
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-decoration: none;
    text-transform: uppercase;
}

.body {
    background-color: {{ $palette->canvas }};
    border-bottom: 1px solid {{ $palette->canvas }};
    border-top: 1px solid {{ $palette->canvas }};
    margin: 0;
    padding: 0;
    width: 100%;
}

.inner-body {
    background-color: {{ $palette->surface }};
    border: 1px solid {{ $palette->border }};
    border-radius: 6px;
    margin: 0 auto;
    padding: 0;
    width: 570px;
}

.content-cell {
    max-width: 100vw;
    padding: 36px 40px;
}

h1 {
    color: {{ $palette->text }};
    font-family: {!! $sans !!};
    font-size: 20px;
    font-weight: 600;
    letter-spacing: -0.01em;
    margin: 0 0 20px;
    text-align: left;
}

h2, h3 {
    color: {{ $palette->text }};
    font-family: {!! $sans !!};
    font-size: 15px;
    font-weight: 600;
    margin: 28px 0 10px;
    text-align: left;
}

p {
    color: {{ $palette->textSecondary }};
    font-family: {!! $sans !!};
    font-size: 15px;
    line-height: 1.65;
    margin: 0 0 16px;
    text-align: left;
}

a {
    color: {{ $palette->accent }};
    text-decoration: underline;
    word-break: break-word;
}

strong {
    color: {{ $palette->text }};
    font-weight: 600;
}

hr {
    border: none;
    border-top: 1px solid {{ $palette->borderSubtle }};
    margin: 28px 0;
}

code {
    background-color: {{ $palette->embedded }};
    border: 1px solid {{ $palette->borderSubtle }};
    border-radius: 3px;
    color: {{ $palette->text }};
    font-family: {!! $mono !!};
    font-size: 13px;
    padding: 1px 5px;
}

blockquote {
    border-left: 2px solid {{ $palette->accent }};
    color: {{ $palette->textSecondary }};
    margin: 0 0 16px;
    padding: 2px 0 2px 16px;
}

.action {
    margin: 28px 0;
    padding: 0;
    text-align: center;
    width: 100%;
}

.button {
    border-radius: 5px;
    display: inline-block;
    font-family: {!! $sans !!};
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 0.01em;
    padding: 12px 26px;
    text-decoration: none;
    -webkit-text-size-adjust: none;
}

.button-primary {
    background-color: {{ $palette->accent }};
    border: 1px solid {{ $palette->accent }};
    color: {{ $palette->onAccent }};
}

.button-success {
    background-color: #529E72;
    border: 1px solid #529E72;
    color: {{ $palette->onAccent }};
}

.button-error {
    background-color: {{ \App\Support\Theme\Palette::ALERT }};
    border: 1px solid {{ \App\Support\Theme\Palette::ALERT }};
    color: #FFFFFF;
}

.panel {
    border-left: 2px solid {{ $palette->accent }};
    margin: 24px 0;
}

.panel-content {
    background-color: {{ $palette->embedded }};
    color: {{ $palette->textSecondary }};
    padding: 16px 20px;
}

.panel-content p {
    color: {{ $palette->textSecondary }};
    margin: 0;
}

.panel-item {
    padding: 0;
}

.panel-item p:last-of-type {
    margin-bottom: 0;
    padding-bottom: 0;
}

.table table {
    margin: 24px auto;
    width: 100%;
}

.table th {
    border-bottom: 1px solid {{ $palette->border }};
    color: {{ $palette->textMuted }};
    font-family: {!! $mono !!};
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.06em;
    margin: 0;
    padding-bottom: 10px;
    text-transform: uppercase;
}

.table td {
    border-bottom: 1px solid {{ $palette->borderSubtle }};
    color: {{ $palette->textSecondary }};
    font-size: 14px;
    margin: 0;
    padding: 12px 0;
}

.content-cell img {
    max-width: 100%;
}

.subcopy {
    border-top: 1px solid {{ $palette->borderSubtle }};
    margin-top: 28px;
    padding-top: 20px;
}

.subcopy p {
    color: {{ $palette->textMuted }};
    font-size: 13px;
    line-height: 1.6;
}

.subcopy a {
    color: {{ $palette->textMuted }};
}

.footer {
    margin: 0 auto;
    padding: 0;
    text-align: center;
    width: 570px;
}

.footer p {
    margin: 0;
    color: {{ $palette->textMuted }};
    font-family: {!! $mono !!};
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.04em;
    line-height: 1.6;
    text-align: center;
}

.footer a {
    color: {{ $palette->textMuted }};
    text-decoration: underline;
}
