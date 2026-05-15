<?php

namespace App\Http\Controllers;

use App\Jobs\SyncRoadmapJob;
use App\Jobs\SyncStatusJob;
use App\Jobs\SyncWikiJob;
use App\Models\GithubWebhookEvent;
use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GitHubWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $event = (string) $request->header('X-GitHub-Event', '');
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $repository = $this->syncRepositoryFromPayload($payload);

        if ($repository) {
            $this->routeEvent($event, $payload, $repository);
        }

        GithubWebhookEvent::create([
            'repository_id' => $repository?->id,
            'event' => $event !== '' ? $event : 'unknown',
            'action' => is_string($payload['action'] ?? null) ? (string) $payload['action'] : null,
            'github_delivery' => is_string($request->header('X-GitHub-Delivery'))
                ? $request->header('X-GitHub-Delivery')
                : null,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncRepositoryFromPayload(array $payload): ?Repository
    {
        $r = $payload['repository'] ?? null;
        if (! is_array($r) || ! isset($r['id'], $r['name'], $r['full_name'])) {
            return null;
        }

        return Repository::updateOrCreate(
            ['github_id' => $r['id']],
            [
                'name' => $r['name'],
                'full_name' => $r['full_name'],
                'description' => $r['description'] ?? null,
                'default_branch' => $r['default_branch'] ?? null,
                'is_private' => (bool) ($r['private'] ?? false),
                'topics' => $r['topics'] ?? [],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function routeEvent(string $event, array $payload, Repository $repository): void
    {
        match ($event) {
            'push' => $this->onPush($payload, $repository),
            'issues' => $this->onIssues($payload, $repository),
            'pull_request' => $this->onPullRequest($payload, $repository),
            'workflow_run' => $this->onWorkflowRun($payload, $repository),
            'release' => $this->onRelease($payload, $repository),
            'milestone' => $this->dispatchSyncStatus($repository),
            'gollum' => $this->dispatchSyncWiki($repository),
            default => null,
        };
    }

    private function dispatchSyncStatus(Repository $repository): void
    {
        SyncStatusJob::dispatch($repository);
    }

    private function dispatchSyncWiki(Repository $repository): void
    {
        SyncWikiJob::dispatch($repository);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onPush(array $payload, Repository $repository): void
    {
        $default = (string) ($repository->default_branch ?? data_get($payload, 'repository.default_branch', 'main'));
        $ref = (string) ($payload['ref'] ?? '');
        if ($ref === 'refs/heads/'.$default) {
            SyncRoadmapJob::dispatch($repository);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onIssues(array $payload, Repository $repository): void
    {
        $action = (string) ($payload['action'] ?? '');
        if (in_array($action, ['opened', 'closed', 'reopened'], true)) {
            SyncStatusJob::dispatch($repository);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onPullRequest(array $payload, Repository $repository): void
    {
        $action = (string) ($payload['action'] ?? '');
        if (in_array($action, ['opened', 'closed'], true)) {
            SyncStatusJob::dispatch($repository);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onWorkflowRun(array $payload, Repository $repository): void
    {
        if (($payload['action'] ?? '') === 'completed') {
            SyncStatusJob::dispatch($repository);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onRelease(array $payload, Repository $repository): void
    {
        if (($payload['action'] ?? '') === 'published') {
            SyncStatusJob::dispatch($repository);
        }
    }
}
