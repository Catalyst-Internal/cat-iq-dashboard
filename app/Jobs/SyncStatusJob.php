<?php

namespace App\Jobs;

use App\Enums\CiState;
use App\Models\Milestone;
use App\Models\Repository;
use App\Models\StatusSnapshot;
use App\Services\GitHubAppService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncStatusJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Repository $repository
    ) {}

    public function handle(GitHubAppService $github): void
    {
        $repo = $this->repository->fresh();
        if (! $repo) {
            return;
        }

        [$owner, $name] = $repo->ownerAndRepo();
        $prefix = '/repos/'.rawurlencode($owner).'/'.rawurlencode($name);

        $runsPayload = $github->getJsonAllow404($prefix.'/actions/runs', ['per_page' => 1]);
        $latestRun = is_array($runsPayload) && isset($runsPayload['workflow_runs'][0])
            ? $runsPayload['workflow_runs'][0]
            : null;

        $ciState = $this->mapCiState($latestRun);

        $repoMeta = $github->getJson($prefix);
        $openIssues = (int) ($repoMeta['open_issues_count'] ?? 0);

        try {
            $search = $github->getJson('/search/issues', [
                'q' => 'repo:'.$repo->full_name.' is:pr is:open',
                'per_page' => 1,
            ]);
            $openPrs = (int) ($search['total_count'] ?? 0);
        } catch (\Throwable) {
            $pullsList = $github->get($prefix.'/pulls', ['state' => 'open', 'per_page' => 100]);
            $openPrs = is_array($pullsList) ? count($pullsList) : 0;
        }

        $latestReleasePayload = $github->getJsonAllow404($prefix.'/releases/latest');
        $latestRelease = null;
        if (is_array($latestReleasePayload)) {
            $latestRelease = $latestReleasePayload['tag_name']
                ?? $latestReleasePayload['name']
                ?? null;
        }

        $tags = $github->get($prefix.'/tags', ['per_page' => 1]);
        $latestTag = is_array($tags) && isset($tags[0]['name']) ? (string) $tags[0]['name'] : null;

        $milestones = $github->get($prefix.'/milestones', ['state' => 'all']);
        $githubMilestoneIds = [];

        if (is_array($milestones)) {
            foreach ($milestones as $m) {
                if (! is_array($m) || ! isset($m['id'])) {
                    continue;
                }
                $githubMilestoneIds[] = $m['id'];
                Milestone::updateOrCreate(
                    ['github_id' => $m['id']],
                    [
                        'repository_id' => $repo->id,
                        'title' => (string) ($m['title'] ?? ''),
                        'state' => (string) ($m['state'] ?? 'open'),
                        'due_on' => ! empty($m['due_on']) ? Carbon::parse((string) $m['due_on'])->toDateString() : null,
                        'open_issues' => (int) ($m['open_issues'] ?? 0),
                        'closed_issues' => (int) ($m['closed_issues'] ?? 0),
                    ]
                );
            }
        }

        if ($githubMilestoneIds === []) {
            Milestone::where('repository_id', $repo->id)->delete();
        } else {
            Milestone::where('repository_id', $repo->id)
                ->whereNotIn('github_id', $githubMilestoneIds)
                ->delete();
        }

        $now = now();

        StatusSnapshot::updateOrCreate(
            ['repository_id' => $repo->id],
            [
                'ci_state' => $ciState,
                'open_issues' => $openIssues,
                'open_prs' => $openPrs,
                'latest_release' => $latestRelease,
                'latest_tag' => $latestTag,
                'synced_at' => $now,
            ]
        );

        $repo->forceFill(['last_synced_at' => $now])->save();
    }

    /**
     * @param  array<string, mixed>|null  $run
     */
    private function mapCiState(?array $run): CiState
    {
        if ($run === null) {
            return CiState::None;
        }

        $status = (string) ($run['status'] ?? '');
        $conclusion = (string) ($run['conclusion'] ?? '');

        if (in_array($status, ['queued', 'in_progress', 'waiting', 'requested', 'pending'], true)) {
            return CiState::Pending;
        }

        if ($status === 'completed') {
            if ($conclusion === 'success' || $conclusion === 'skipped' || $conclusion === 'neutral') {
                return CiState::Passing;
            }

            if (in_array($conclusion, ['failure', 'cancelled', 'timed_out', 'action_required'], true)) {
                return CiState::Failing;
            }

            return CiState::Passing;
        }

        return CiState::Pending;
    }
}
