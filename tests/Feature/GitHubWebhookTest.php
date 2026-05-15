<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class GitHubWebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * GitHub signs the raw request body; use a literal POST body (not postJson re-encoding).
     *
     * @param  array<string, string>  $headers
     */
    private function postGitHubWebhookRaw(string $body, array $headers): \Illuminate\Testing\TestResponse
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', '/webhooks/github', [], [], [], $server, $body);
    }

    public function test_webhook_rejects_bad_signature(): void
    {
        $this->postJson('/webhooks/github', ['repository' => ['id' => 1]], [
            'X-GitHub-Event' => 'milestone',
            'X-Hub-Signature-256' => 'sha256=deadbeef',
        ])->assertStatus(403);
    }

    public function test_milestone_webhook_dispatches_sync_status(): void
    {
        Bus::fake();

        $body = json_encode([
            'action' => 'created',
            'repository' => [
                'id' => 4242,
                'name' => 'demo',
                'full_name' => 'catalyst-internal/demo',
                'default_branch' => 'main',
                'private' => false,
                'topics' => [],
            ],
        ], JSON_THROW_ON_ERROR);

        $secret = (string) config('github.webhook_secret');
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

        $this->postGitHubWebhookRaw($body, [
            'X-GitHub-Event' => 'milestone',
            'X-Hub-Signature-256' => $signature,
        ])->assertOk();

        $this->assertDatabaseHas('repositories', [
            'github_id' => 4242,
            'name' => 'demo',
        ]);

        Bus::assertDispatched(\App\Jobs\SyncStatusJob::class);

        $this->assertDatabaseHas('github_webhook_events', [
            'event' => 'milestone',
            'action' => 'created',
        ]);
    }

    public function test_webhook_logs_event_when_repository_missing_from_payload(): void
    {
        Bus::fake();

        $body = json_encode(['zen' => 'anything'], JSON_THROW_ON_ERROR);
        $secret = (string) config('github.webhook_secret');
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

        $this->postGitHubWebhookRaw($body, [
            'X-GitHub-Event' => 'ping',
            'X-Hub-Signature-256' => $signature,
        ])->assertOk();

        Bus::assertNotDispatched(\App\Jobs\SyncStatusJob::class);
        Bus::assertNotDispatched(\App\Jobs\SyncWikiJob::class);
        Bus::assertNotDispatched(\App\Jobs\SyncRoadmapJob::class);

        $this->assertDatabaseHas('github_webhook_events', [
            'event' => 'ping',
            'repository_id' => null,
        ]);
    }
}
