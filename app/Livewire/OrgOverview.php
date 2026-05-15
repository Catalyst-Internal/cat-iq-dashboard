<?php

namespace App\Livewire;

use App\Enums\CiState;
use App\Models\Milestone;
use App\Models\Repository;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Poll;
use Livewire\Component;

#[Layout('layouts.dashboard')]
class OrgOverview extends Component
{
    /** Poll every 30s (handoff: ~30s refresh for org overview). */
    #[Poll(30)]
    public function render(): View
    {
        $repositories = Repository::query()
            ->with([
                'statusSnapshot',
                'milestones' => fn ($q) => $q->where('state', 'open'),
            ])
            ->orderBy('name')
            ->get();

        return view('livewire.org-overview', [
            'repositories' => $repositories,
        ]);
    }

    public function pillState(Repository $repo): string
    {
        $ci = $repo->statusSnapshot?->ci_state;

        if ($ci === CiState::Failing) {
            return 'failing';
        }

        if ($ci === null || $ci === CiState::None) {
            return 'none';
        }

        if ($ci === CiState::Pending) {
            return 'pending';
        }

        if ($this->hasOverdueOpenMilestone($repo)) {
            return 'overdue';
        }

        return 'passing';
    }

    private function hasOverdueOpenMilestone(Repository $repo): bool
    {
        $today = now()->startOfDay();

        return $repo->milestones
            ->where('state', 'open')
            ->contains(fn (Milestone $m) => $m->due_on && $m->due_on->lt($today));
    }

    public function activeMilestoneLabel(Repository $repo): string
    {
        $open = $repo->milestones->where('state', 'open');
        if ($open->isEmpty()) {
            return 'No active milestone';
        }

        $today = now()->startOfDay();
        $future = $open
            ->filter(fn (Milestone $m) => $m->due_on && $m->due_on->gte($today))
            ->sortBy(fn (Milestone $m) => $m->due_on->timestamp)
            ->first();
        if ($future) {
            return $future->title;
        }

        $past = $open
            ->filter(fn (Milestone $m) => $m->due_on && $m->due_on->lt($today))
            ->sortByDesc(fn (Milestone $m) => $m->due_on->timestamp)
            ->first();
        if ($past) {
            return $past->title;
        }

        return (string) ($open->first()?->title ?? '');
    }
}
