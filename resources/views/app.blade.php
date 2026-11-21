@php
    use App\Support\Color;
    use App\Support\Media;

    $settings = \App\Models\BusinessSetting::current();
    $primary = Color::sanitize($settings->primary_color, '#111827');
    $secondary = Color::sanitize($settings->secondary_color, '#F4F1EC');
    $accent = Color::sanitize($settings->accent_color, '#C2410C');
    $favicon = Media::url($settings->favicon_path);
    // Storefront extras (verification, custom meta/code, pixels) never load in the admin.
    $storefront = ! request()->is('admin', 'admin/*');
    $tracking = $storefront ? \App\Support\Tracking::config(request(), $settings) : [];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $primary }}">
    <meta name="author" content="{{ config('catalog.author.name') }}">

    @isset($seo)
        <title inertia>{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        <meta name="robots" content="{{ $seo['robots'] }}">
        <link rel="canonical" href="{{ $seo['canonical'] }}">

        <meta property="og:site_name" content="{{ $seo['site_name'] }}">
        <meta property="og:type" content="{{ $seo['type'] }}">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
        @if ($seo['image'])
            <meta property="og:image" content="{{ $seo['image'] }}">
        @endif
        <meta name="twitter:card" content="{{ $seo['image'] ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $seo['title'] }}">
        <meta name="twitter:description" content="{{ $seo['description'] }}">
        @if ($seo['image'])
            <meta name="twitter:image" content="{{ $seo['image'] }}">
        @endif
        @if ($seo['twitter_site'] ?? null)
            <meta name="twitter:site" content="{{ $seo['twitter_site'] }}">
        @endif
        @foreach ($seo['product'] ?? [] as $property => $content)
            <meta property="{{ $property }}" content="{{ $content }}">
        @endforeach

        @foreach ($seo['schemas'] as $schema)
            <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endforeach
    @else
        <title inertia>{{ $settings->business_name }}</title>
        <meta name="robots" content="noindex, nofollow">
    @endisset

    @if ($storefront)
        @include('partials.head-extras', ['settings' => $settings, 'tracking' => $tracking])
    @endif

    @if ($favicon)
        <link rel="icon" type="image/png" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @endif

    <style>
        :root {
            --brand-primary: {{ $primary }};
            --brand-on-primary: {{ Color::contrast($primary) }};
            --brand-secondary: {{ $secondary }};
            --brand-on-secondary: {{ Color::contrast($secondary) }};
            --brand-accent: {{ $accent }};
            --brand-on-accent: {{ Color::contrast($accent) }};
        }
    </style>

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
    @inertiaHead
</head>
<body class="antialiased">
    @if ($storefront)
        @if ($tracking['gtm'] ?? null)
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $tracking['gtm'] }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        @endif
        {!! $settings->body_code !!}
    @endif
    @inertia
    @if ($storefront)
        {!! $settings->footer_code !!}
    @endif
</body>
</html>
