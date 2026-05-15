<div>
    <div class="mb-6">
        <a href="{{ url('/') }}" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">&larr; All repos</a>
        <flux:heading size="xl" class="mt-2">{{ $repository->full_name }}</flux:heading>
        @if ($repository->description)
            <flux:text class="mt-1 text-zinc-600 dark:text-zinc-400">{{ $repository->description }}</flux:text>
        @endif
    </div>

    <flux:separator class="my-8" />

    <flux:heading size="lg" class="mb-4">Status</flux:heading>

    <div class="mb-8 space-y-6">
        <div>
            <flux:subheading class="mb-2">Open milestones</flux:subheading>
            @forelse ($repository->milestones as $m)
                <div class="mb-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium">{{ $m->title }}</span>
                        @if ($m->due_on)
                            <span class="rounded-md bg-zinc-100 px-2 py-0.5 text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                Due {{ $m->due_on->format('M j, Y') }}
                            </span>
                        @endif
                    </div>
                    <div class="mt-2 text-xs text-zinc-500">
                        {{ $m->open_issues }} open · {{ $m->closed_issues }} closed
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                        <div
                            class="h-full bg-emerald-500 transition-all"
                            style="width: {{ $m->progressPercent() }}%"
                        ></div>
                    </div>
                </div>
            @empty
                <flux:text class="text-zinc-500">No open milestones.</flux:text>
            @endforelse
        </div>

        <div>
            <flux:subheading class="mb-2">Last workflow runs</flux:subheading>
            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-800">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                        <tr>
                            <th class="px-3 py-2">Workflow</th>
                            <th class="px-3 py-2">Branch</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($runs as $run)
                            <tr wire:key="run-{{ $run['id'] ?? $loop->index }}" class="border-t border-zinc-200 dark:border-zinc-800">
                                <td class="px-3 py-2 font-medium">{{ $run['name'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ $run['head_branch'] ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    {{ $run['status'] ?? '—' }}
                                    @if (! empty($run['conclusion']))
                                        <span class="text-zinc-500">/ {{ $run['conclusion'] }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">
                                    {{ isset($run['created_at']) ? \Illuminate\Support\Carbon::parse($run['created_at'])->diffForHumans() : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-zinc-500">No workflow runs returned.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <flux:subheading class="mb-2">Open pull requests</flux:subheading>
            <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                @forelse ($pulls as $pr)
                    <li wire:key="pr-{{ $pr['id'] ?? $loop->index }}" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                        <a
                            href="{{ data_get($pr, 'html_url', '#') }}"
                            class="font-medium text-blue-600 hover:underline dark:text-blue-400"
                            target="_blank"
                            rel="noopener noreferrer"
                        >{{ $pr['title'] ?? '—' }}</a>
                        <span class="text-sm text-zinc-500">
                            {{ data_get($pr, 'user.login', 'unknown') }}
                            · {{ isset($pr['created_at']) ? \Illuminate\Support\Carbon::parse($pr['created_at'])->diffForHumans() : '' }}
                        </span>
                    </li>
                @empty
                    <li class="px-3 py-4 text-zinc-500">No open PRs.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <flux:separator class="my-8" />

    <flux:heading size="lg" class="mb-4">Roadmap</flux:heading>
    @if ($roadmapGrouped->isEmpty())
        <flux:text class="text-zinc-500">No roadmap entries synced (add ROADMAP.md on the default branch).</flux:text>
    @else
        <div class="space-y-8">
            @foreach ($roadmapGrouped as $phase => $byMilestone)
                <div wire:key="phase-{{ \Illuminate\Support\Str::slug($phase) }}">
                    <flux:heading size="md" class="mb-3">{{ $phase }}</flux:heading>
                    @foreach ($byMilestone as $milestoneTitle => $entries)
                        <div class="mb-4 ms-2 border-l-2 border-zinc-200 ps-4 dark:border-zinc-700" wire:key="ms-{{ \Illuminate\Support\Str::slug($phase.'-'.$milestoneTitle) }}">
                            @if ($milestoneTitle !== '')
                                <flux:subheading class="mb-2">{{ $milestoneTitle }}</flux:subheading>
                            @endif
                            <ul class="space-y-2">
                                @foreach ($entries as $entry)
                                    @php
                                        $border = match ($entry->item_type) {
                                            \App\Enums\RoadmapItemType::Feature => 'border-l-4 border-blue-500 pl-3',
                                            \App\Enums\RoadmapItemType::Patch => 'border-l-4 border-amber-500 pl-3',
                                            default => 'border-l-4 border-zinc-300 pl-3 dark:border-zinc-600',
                                        };
                                    @endphp
                                    <li wire:key="rm-{{ $entry->id }}" class="{{ $border }}">{{ $entry->item_text }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    <flux:separator class="my-8" />

    <flux:heading size="lg" class="mb-4">Wiki</flux:heading>
    @if ($repository->wikiPages->isEmpty())
        <flux:text class="text-zinc-500">No wiki pages synced.</flux:text>
    @else
        <div class="space-y-2">
            @foreach ($repository->wikiPages as $page)
                <div wire:key="wiki-{{ $page->id }}" class="rounded-lg border border-zinc-200 dark:border-zinc-800">
                    <button
                        type="button"
                        wire:click="toggleWiki(@js($page->slug))"
                        class="flex w-full items-center justify-between px-4 py-3 text-left font-medium hover:bg-zinc-50 dark:hover:bg-zinc-900"
                    >
                        <span>{{ $page->title }}</span>
                        <span class="text-xs text-zinc-500">{{ $page->slug }}.md</span>
                    </button>
                    @if ($expandedWikiSlug === $page->slug)
                        <div class="border-t border-zinc-200 px-4 py-3 text-sm leading-relaxed dark:border-zinc-800 [&_a]:text-blue-600 [&_a]:underline dark:[&_a]:text-blue-400">
                            {!! $wikiHtml[$page->slug] ?? '' !!}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
