@extends('install.layout', ['title' => 'Verify ownership'])

@section('content')
    <h1 class="text-2xl font-bold">Confirm this is your website</h1>
    <p class="mt-2 text-sm text-zinc-600">So that nobody else can set up your website before you, enter the setup code saved on your hosting account.</p>

    <ol class="mt-4 list-decimal space-y-1.5 pl-5 text-sm text-zinc-700">
        <li>Sign in to your hosting control panel (for example cPanel) and open <strong>File Manager</strong>.</li>
        <li>Open your website’s folder and turn on <strong>Show hidden files</strong> (in cPanel: <em>Settings</em>, top right).</li>
        <li>Open the file named <strong>.env</strong> and find the line that starts with <code class="rounded bg-zinc-100 px-1">INSTALL_SETUP_TOKEN=</code></li>
        <li>Copy everything after the <strong>=</strong> sign and paste it below.</li>
    </ol>

    <form method="POST" action="{{ route('install.unlock') }}" class="mt-5 space-y-4">
        @csrf
        @include('install.field', ['name' => 'setup_token', 'label' => 'Setup code', 'type' => 'password'])
        <button class="rounded bg-zinc-900 px-4 py-2 text-white">Continue</button>
    </form>

    <p class="mt-4 text-xs text-zinc-500">If that line is empty, type a long random password of at least 32 characters after the = sign, save the file, and use the same text here.</p>
@endsection
