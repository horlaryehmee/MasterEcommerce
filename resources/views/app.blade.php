@php
    use App\Support\Color;
    use App\Support\Media;

    $settings = \App\Models\BusinessSetting::current();
    // Storefront pages show a previewed template's colours and fonts (nothing is saved).
    $storefront = ! request()->is('admin', 'admin/*');
    if ($storefront) {
        $settings = \App\Support\Themes::effectiveSettings($settings);
    }
    $primary = Color::sanitize($settings->primary_color, '#111827');
    $secondary = Color::sanitize($settings->secondary_color, '#F4F1EC');
    $accent = Color::sanitize($settings->accent_color, '#C2410C');
    $favicon = Media::url($settings->favicon_path);
    // Storefront extras (verification, custom meta/code, pixels) never load in the admin.
    $tracking = $storefront ? \App\Support\Tracking::config(request(), $settings) : [];
    // Brand fonts and button shape apply to the storefront only; the admin keeps its own look.
    $brand = $storefront ? \App\Support\Fonts::forSettings($settings) : ['stylesheet' => null, 'vars' => []];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @if ($storefront)
        <meta name="stats-csrf" content="{{ csrf_token() }}">
    @endif
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="xsrf-cookie" content="{{ \App\Http\Middleware\ValidateCsrfToken::cookieName() }}">
    <meta name="theme-color" content="{{ $storefront ? $primary : '#FFFFFF' }}">
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

    @if ($storefront && ! \App\Support\Tracking::privatePage(request()))
        @include('partials.head-extras', ['settings' => $settings, 'tracking' => $tracking])
    @endif

    @if ($favicon)
        <link rel="icon" type="image/png" href="{{ $favicon }}">
    @endif

    {{-- Installable app: the admin and the shop install as two separate apps. --}}
    @php($pwaApp = $storefront ? 'shop' : 'admin')
    <link rel="manifest" href="{{ $storefront ? route('pwa.manifest') : route('admin.manifest') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ $storefront ? \Illuminate\Support\Str::limit($settings->business_name, 12, '') : 'Admin' }}">
    @if ($touchIcon = \App\Support\AppIcon::url($pwaApp, 180))
        <link rel="apple-touch-icon" href="{{ $touchIcon }}">
    @elseif ($favicon)
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @endif
    <meta name="sw-url" content="{{ route('pwa.sw') }}">

    @if ($brand['stylesheet'])
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="{{ $brand['stylesheet'] }}">
    @endif

    <style>
        :root {
@foreach ($brand['vars'] as $name => $value)
            {{-- Raw: font names are letters, digits and spaces only (App\Support\Fonts::validName). --}}
            {{ $name }}: {!! $value !!};
@endforeach
            --brand-primary: {{ $primary }};
            --brand-on-primary: {{ Color::contrast($primary) }};
            --brand-secondary: {{ $secondary }};
            --brand-on-secondary: {{ Color::contrast($secondary) }};
            --brand-accent: {{ $accent }};
            --brand-on-accent: {{ Color::contrast($accent) }};
        }
@isset($brand['vars']['--brand-heading-weight'])
        body:has(.store-shell) :is(h1, h2) { font-weight: var(--brand-heading-weight); }
@endisset
    </style>

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
    @inertiaHead
</head>
<body class="antialiased">
    @if ($storefront && ! \App\Support\Tracking::privatePage(request()))
        @if ($tracking['gtm'] ?? null)
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $tracking['gtm'] }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        @endif
        {!! $settings->body_code !!}
    @endif
    @inertia
    @if ($storefront && ! \App\Support\Tracking::privatePage(request()))
        {!! $settings->footer_code !!}
    @endif
</body>
</html>
