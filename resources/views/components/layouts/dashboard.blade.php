<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Cat IQ' }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet">
    @fluxAppearance
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-50">
    <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
            <div class="flex items-baseline gap-3">
                <a href="{{ url('/') }}" class="text-lg font-semibold tracking-tight">Cat IQ</a>
                <span class="text-sm text-zinc-500 dark:text-zinc-400">catalyst-internal</span>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Last sync:
                <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ isset($lastGlobalSync) && $lastGlobalSync ? $lastGlobalSync->diffForHumans() : '—' }}</span>
            </p>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8">
        {{ $slot }}
    </main>

    @livewireScripts
    @fluxScripts
</body>
</html>
