<?php

namespace App\Livewire;

use App\Models\Repository;
use App\Services\GitHubAppService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.dashboard')]
class RepoDetail extends Component
{
    public Repository $repository;

    public ?string $expandedWikiSlug = null;

    public function mount(Repository $repository): void
    {
        $this->repository = $repository;
    }

    public function toggleWiki(string $slug): void
    {
        $this->expandedWikiSlug = $this->expandedWikiSlug === $slug ? null : $slug;
    }

    public function render(GitHubAppService $github): View
    {
        $this->repository->load([
            'milestones' => fn ($q) => $q->where('state', 'open')->orderBy('due_on'),
            'roadmapEntries' => fn ($q) => $q->orderBy('sort_order'),
            'wikiPages' => fn ($q) => $q->orderBy('title'),
            'statusSnapshot',
        ]);

        [$owner, $name] = $this->repository->ownerAndRepo();
        $prefix = '/repos/'.rawurlencode($owner).'/'.rawurlencode($name);

        $runs = Cache::remember(
            'catiq:workflow_runs:'.$this->repository->id,
            120,
            function () use ($github, $prefix) {
                $json = $github->getJson($prefix.'/actions/runs', ['per_page' => 5]);

                return $json['workflow_runs'] ?? [];
            }
        );

        $pulls = Cache::remember(
            'catiq:pulls:'.$this->repository->id,
            120,
            function () use ($github, $prefix) {
                return $github->get($prefix.'/pulls', ['state' => 'open', 'per_page' => 100]);
            }
        );

        $roadmapGrouped = $this->repository->roadmapEntries
            ->groupBy('phase')
            ->map(fn ($byPhase) => $byPhase->groupBy(fn ($e) => $e->milestone_title ?? ''));

        $wikiHtml = [];
        foreach ($this->repository->wikiPages as $page) {
            $wikiHtml[$page->slug] = Str::markdown($page->content_md, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
        }

        return view('livewire.repo-detail', [
            'runs' => is_array($runs) ? $runs : [],
            'pulls' => is_array($pulls) ? $pulls : [],
            'roadmapGrouped' => $roadmapGrouped,
            'wikiHtml' => $wikiHtml,
        ]);
    }
}
