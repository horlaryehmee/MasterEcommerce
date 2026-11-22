{{-- Coming soon / maintenance page (Website Settings → Coming soon & maintenance). Plain HTML so it loads instantly. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $heading }} · {{ $name }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($message, 155) }}">
    <meta name="theme-color" content="{{ $primary }}">
    @if ($favicon)
        <link rel="icon" href="{{ $favicon }}">
    @endif
    @if ($fontStylesheet)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="{{ $fontStylesheet }}">
    @endif
    <style>
        :root {
            --primary: {{ $primary }};
            --accent: {{ $accent }};
            @foreach ($fontVars as $var => $value) {{ $var }}: {!! e($value) !!}; @endforeach
        }
        * { box-sizing: border-box; margin: 0; }
        html, body { height: 100%; }
        body {
            font-family: var(--brand-font-body, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif);
            color: #1c1917; background: #fafaf9; -webkit-font-smoothing: antialiased;
        }
        .page { min-height: 100%; display: grid; place-items: center; padding: 32px 16px; position: relative; overflow: hidden; }
        .backdrop { position: absolute; inset: 0; z-index: 0; }
        .backdrop.plain { background:
            radial-gradient(60rem 40rem at 10% -10%, color-mix(in srgb, var(--primary) 18%, transparent), transparent 60%),
            radial-gradient(50rem 35rem at 110% 110%, color-mix(in srgb, var(--accent) 16%, transparent), transparent 60%), #fafaf9; }
        .backdrop.photo { background-size: cover; background-position: center; }
        .backdrop.photo::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(12,10,9,.55), rgba(12,10,9,.72)); }
        .card { position: relative; z-index: 1; width: 100%; max-width: 640px; text-align: center; }
        .on-photo { color: #fff; }
        .logo { max-height: 56px; max-width: 220px; margin: 0 auto 28px; display: block; }
        .logo-chip { display: inline-flex; padding: 10px 16px; border-radius: 16px; background: rgba(255,255,255,.92); margin-bottom: 28px; }
        .logo-chip .logo { margin: 0; max-height: 44px; }
        .name { font-family: var(--brand-font-heading, inherit); font-weight: 700; font-size: 20px; margin-bottom: 28px; letter-spacing: -.01em; }
        .badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border-radius: 999px; font-size: 13px; font-weight: 600;
            background: color-mix(in srgb, var(--primary) 10%, white); color: var(--primary); }
        .on-photo .badge { background: rgba(255,255,255,.16); color: #fff; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--accent); animation: pulse 1.8s ease-in-out infinite; }
        @keyframes pulse { 50% { opacity: .35; } }
        h1 { font-family: var(--brand-font-heading, inherit); font-weight: var(--brand-heading-weight, 700); font-size: clamp(32px, 7vw, 56px); line-height: 1.08; letter-spacing: -.02em; margin: 18px 0 14px; text-wrap: balance; }
        .message { font-size: clamp(16px, 2.6vw, 18px); line-height: 1.6; color: #57534e; max-width: 520px; margin: 0 auto; white-space: pre-line; }
        .on-photo .message { color: rgba(255,255,255,.88); }
        .countdown { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; max-width: 460px; margin: 32px auto 8px; }
        .unit { background: #fff; border: 1px solid #e7e5e4; border-radius: 18px; padding: 16px 6px 12px; box-shadow: 0 10px 30px -18px rgba(28,25,23,.35); }
        .on-photo .unit { background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.2); box-shadow: none; backdrop-filter: blur(6px); }
        .num { font-size: clamp(28px, 7vw, 42px); font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: -.02em; line-height: 1; }
        .label { margin-top: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: #78716c; }
        .on-photo .label { color: rgba(255,255,255,.7); }
        .when { font-size: 14px; color: #78716c; margin-top: 10px; }
        .on-photo .when { color: rgba(255,255,255,.75); }
        .opening { margin-top: 28px; font-weight: 600; font-size: 18px; }
        .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 32px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; min-height: 48px; padding: 0 20px; border-radius: var(--btn-radius, 999px); font-weight: 600; font-size: 15px; text-decoration: none; transition: transform .15s, opacity .15s; }
        .btn:active { transform: scale(.97); }
        .btn-wa { background: #25D366; color: #fff; }
        .btn-plain { background: #fff; color: #1c1917; border: 1px solid #d6d3d1; }
        .on-photo .btn-plain { background: rgba(255,255,255,.14); color: #fff; border-color: rgba(255,255,255,.3); }
        .socials { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 18px; margin-top: 22px; font-size: 14px; }
        .socials a { color: inherit; opacity: .75; text-decoration: none; } .socials a:hover { opacity: 1; text-decoration: underline; }
        .foot { margin-top: 40px; font-size: 12px; opacity: .6; }
        @media (max-width: 380px) { .countdown { gap: 6px; } .unit { border-radius: 14px; padding: 12px 2px 10px; } }
        @media (prefers-reduced-motion: reduce) { .dot { animation: none; } }
    </style>
</head>
<body>
<main class="page">
    <div class="backdrop {{ $image ? 'photo' : 'plain' }}" @if ($image) style="background-image: url('{{ $image }}')" @endif></div>

    <div class="card {{ $image ? 'on-photo' : '' }}">
        @if ($logo)
            @if ($image)<span class="logo-chip"><img class="logo" src="{{ $logo }}" alt="{{ $name }}"></span>@else<img class="logo" src="{{ $logo }}" alt="{{ $name }}">@endif
        @else
            <p class="name">{{ $name }}</p>
        @endif

        <span class="badge"><span class="dot" aria-hidden="true"></span>{{ $mode === 'maintenance' ? 'Down for maintenance' : 'Coming soon' }}</span>
        <h1>{{ $heading }}</h1>
        <p class="message">{{ $message }}</p>

        @if ($launchAt)
            <div class="countdown" id="countdown" data-at="{{ $launchAt }}" data-auto="{{ $autoOpen ? 1 : 0 }}" role="timer" aria-live="off">
                <div class="unit"><div class="num" data-u="d">--</div><div class="label">Days</div></div>
                <div class="unit"><div class="num" data-u="h">--</div><div class="label">Hours</div></div>
                <div class="unit"><div class="num" data-u="m">--</div><div class="label">Minutes</div></div>
                <div class="unit"><div class="num" data-u="s">--</div><div class="label">Seconds</div></div>
            </div>
            <p class="when" id="when"></p>
            <p class="opening" id="opening" hidden></p>
        @endif

        @if ($whatsapp || $email || $phone)
            <div class="actions">
                @if ($whatsapp)
                    <a class="btn btn-wa" href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.8.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.5-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3.1 4.9 4.3.7.3 1.2.5 1.7.6.7.2 1.3.2 1.8.1.6-.1 1.7-.7 1.9-1.4.2-.7.2-1.2.2-1.4-.1-.1-.3-.2-.6-.3zM12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2z"/></svg>
                        Chat on WhatsApp
                    </a>
                @endif
                @if ($phone)<a class="btn btn-plain" href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">Call {{ $phone }}</a>@endif
                @if ($email)<a class="btn btn-plain" href="mailto:{{ $email }}">Email us</a>@endif
            </div>
        @endif

        @if ($socials)
            <nav class="socials" aria-label="Social media">
                @foreach ($socials as $social)
                    <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer">{{ $social['label'] }}</a>
                @endforeach
            </nav>
        @endif

        <p class="foot">© {{ date('Y') }} {{ $name }}</p>
    </div>
</main>

@if ($launchAt)
<script>
(function () {
    var box = document.getElementById('countdown');
    var at = new Date(box.dataset.at).getTime();
    var auto = box.dataset.auto === '1';
    var when = document.getElementById('when');
    var opening = document.getElementById('opening');
    var pad = function (n) { return String(n).padStart(2, '0'); };
    try {
        when.textContent = 'Opening ' + new Date(at).toLocaleString(undefined, { weekday: 'long', day: 'numeric', month: 'long', hour: 'numeric', minute: '2-digit' });
    } catch (e) {}
    function tick() {
        var left = Math.max(0, at - Date.now());
        var s = Math.floor(left / 1000);
        box.querySelector('[data-u=d]').textContent = Math.floor(s / 86400);
        box.querySelector('[data-u=h]').textContent = pad(Math.floor(s % 86400 / 3600));
        box.querySelector('[data-u=m]').textContent = pad(Math.floor(s % 3600 / 60));
        box.querySelector('[data-u=s]').textContent = pad(s % 60);
        if (left > 0) return;
        clearInterval(timer);
        opening.hidden = false;
        if (auto) {
            opening.textContent = 'We’re open! Taking you to the shop…';
            // A moment's grace for clocks that run slightly fast.
            setTimeout(function () { location.reload(); }, 3000);
        } else {
            opening.textContent = 'Opening any moment now.';
        }
    }
    var timer = setInterval(tick, 1000);
    tick();
})();
</script>
@endif
</body>
</html>
