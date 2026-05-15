@php
    $lastGlobalSync = \App\Models\Repository::max('last_synced_at');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cat IQ — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet">
    @fluxAppearance
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex items-center gap-4">
                <flux:heading size="lg" class="!mb-0">Cat IQ</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">{{ config('github.org') }}</flux:text>
            </div>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                Last sync:
                {{ $lastGlobalSync ? \Illuminate\Support\Carbon::parse($lastGlobalSync)->timezone(config('app.timezone'))->diffForHumans() : 'never' }}
            </flux:text>
        </div>
    </header>
    <main class="mx-auto max-w-7xl px-4 py-6">
        {{ $slot }}
    </main>
    @livewireScripts
    @fluxScripts
</body>
</html>
