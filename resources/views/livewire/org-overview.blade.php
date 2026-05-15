<div>
    <flux:heading size="xl" class="mb-6">Repositories</flux:heading>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($repositories as $repo)
            @php
                $snap = $repo->statusSnapshot;
                $pill = $this->pillState($repo);
                $pillClass = match ($pill) {
                    'failing' => 'bg-red-600 text-white',
                    'pending', 'overdue' => 'bg-amber-500 text-zinc-900',
                    'passing' => 'bg-emerald-600 text-white',
                    default => 'bg-zinc-400 text-white',
                };
                $pillLabel = match ($pill) {
                    'failing' => 'Failing',
                    'pending' => 'Pending',
                    'overdue' => 'Overdue',
                    'passing' => 'Passing',
                    default => 'No CI',
                };
                $releaseOrTag = $snap?->latest_release ?? $snap?->latest_tag ?? '—';
            @endphp
            <a
                wire:key="repo-{{ $repo->id }}"
                href="{{ url('/repos/'.$repo->name) }}"
                wire:navigate
                class="block rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700"
            >
                <div class="mb-3 flex items-start justify-between gap-2">
                    <flux:heading size="md" class="!mb-0 truncate">{{ $repo->name }}</flux:heading>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $pillClass }}">
                        {{ $pillLabel }}
                    </span>
                </div>
                <dl class="space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                    <div class="flex justify-between gap-2">
                        <dt>Open issues</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $snap?->open_issues ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt>Open PRs</dt>
                        <dd class="font-medium text-zinc-900 dark:text-zinc-100">{{ $snap?->open_prs ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt>Release / tag</dt>
                        <dd class="truncate font-medium text-zinc-900 dark:text-zinc-100" title="{{ $releaseOrTag }}">{{ $releaseOrTag }}</dd>
                    </div>
                    <div class="pt-1 text-xs">
                        <span class="text-zinc-500">Milestone:</span>
                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $this->activeMilestoneLabel($repo) }}</span>
                    </div>
                </dl>
            </a>
        @empty
            <flux:text>No repositories synced yet. Run <code class="rounded bg-zinc-100 px-1 py-0.5 text-xs dark:bg-zinc-800">php artisan github:sync-org</code>.</flux:text>
        @endforelse
    </div>
</div>
