{{-- Storefront <head> extras from Website Settings → SEO, Tracking & ads and Custom code. --}}
@php
    $verifications = [
        'google-site-verification' => $settings->verify_google,
        'msvalidate.01' => $settings->verify_bing,
        'yandex-verification' => $settings->verify_yandex,
        'p:domain_verify' => $settings->verify_pinterest,
        'facebook-domain-verification' => $settings->verify_facebook,
    ];
@endphp
@foreach ($verifications as $name => $content)
    @if (filled($content))
        <meta name="{{ $name }}" content="{{ $content }}">
    @endif
@endforeach
@foreach ((array) $settings->custom_meta as $meta)
    @if (filled($meta['key'] ?? null) && in_array($meta['attribute'] ?? '', ['name', 'property', 'http-equiv'], true))
        <meta {{ $meta['attribute'] }}="{{ $meta['key'] }}" content="{{ $meta['content'] ?? '' }}">
    @endif
@endforeach

@if ($tracking['gtm'] ?? null)
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json($tracking['gtm']));</script>
@endif
@if (($tracking['ga4'] ?? null) || ($tracking['ads'] ?? null))
    <!-- Google tag (GA4 / Google Ads) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $tracking['ga4'] ?? $tracking['ads'] }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        @if ($tracking['ga4'] ?? null) gtag('config', @json($tracking['ga4'])); @endif
        @if ($tracking['ads'] ?? null) gtag('config', @json($tracking['ads'])); @endif
    </script>
@endif
@if ($tracking['meta'] ?? null)
    <!-- Meta Pixel -->
    <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',@json($tracking['meta']));fbq('track','PageView');</script>
    <noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id={{ $tracking['meta'] }}&ev=PageView&noscript=1"></noscript>
@endif
@if ($tracking['tiktok'] ?? null)
    <!-- TikTok Pixel -->
    <script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=d.createElement("script");o.type="text/javascript",o.async=!0,o.src=r+"?sdkid="+e+"&lib="+t;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load(@json($tracking['tiktok']));ttq.page();}(window,document,'ttq');</script>
@endif

{!! $settings->head_code !!}
