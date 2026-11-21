<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="author" content="{{ config('catalog.author.name') }}">
    <title>Setup · {{ $title ?? 'Install' }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased">
    <main class="mx-auto max-w-xl px-4 py-10 sm:py-16">
        <p class="text-sm font-semibold tracking-wide text-zinc-500 uppercase">Setup</p>
        <ol class="mt-3 mb-6 flex gap-2 text-xs font-medium" aria-label="Steps">
            @foreach (['Server check', 'Database', 'Your site', 'Done'] as $i => $label)
                <li class="flex-1 rounded-full px-2 py-1.5 text-center {{ $i + 1 === ($step ?? 1) ? 'bg-zinc-900 text-white' : ($i + 1 < ($step ?? 1) ? 'bg-emerald-100 text-emerald-800' : 'bg-zinc-200 text-zinc-500') }}">{{ $label }}</li>
            @endforeach
        </ol>
        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs sm:p-7">
            @yield('content')
        </section>
        <p class="mt-6 text-center text-xs text-zinc-400">This setup page closes for good once installation is complete.</p>
        <p class="mt-2 text-center text-xs text-zinc-400">Software by <a href="{{ config('catalog.author.url') }}" target="_blank" rel="noopener" class="font-medium text-zinc-500 hover:text-zinc-900">{{ config('catalog.author.name') }}</a></p>
    </main>
</body>
</html>
