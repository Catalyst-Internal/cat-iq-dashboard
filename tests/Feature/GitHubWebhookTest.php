<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class GitHubWebhookTest extends TestCase
{
    use RefreshDatabase;

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

        $this->withHeaders([
            'X-GitHub-Event' => 'milestone',
            'X-Hub-Signature-256' => $signature,
            'Content-Type' => 'application/json',
        ])->withBody($body, 'application/json')->post('/webhooks/github')->assertOk();

        $this->assertDatabaseHas('repositories', [
            'github_id' => 4242,
            'name' => 'demo',
        ]);

        Bus::assertDispatched(\App\Jobs\SyncStatusJob::class);
    }
}
