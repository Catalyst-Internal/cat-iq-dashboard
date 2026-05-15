<?php

namespace App\Console\Commands;

use App\Jobs\SyncRepoJob;
use App\Models\Repository;
use App\Services\GitHubAppService;
use Illuminate\Console\Command;

class GithubSyncOrgCommand extends Command
{
    protected $signature = 'github:sync-org';

    protected $description = 'Sync repositories from the configured GitHub org and queue per-repo sync jobs.';

    public function handle(GitHubAppService $github): int
    {
        $org = config('github.org');
        $this->info("Fetching repositories for org [{$org}]...");

        $repos = $github->get("/orgs/{$org}/repos", [
            'type' => 'all',
            'per_page' => 100,
        ]);

        if (! is_array($repos)) {
            $this->error('Unexpected response from GitHub (expected a list of repositories).');

            return self::FAILURE;
        }

        $count = 0;
        foreach ($repos as $payload) {
            if (! is_array($payload) || ! isset($payload['id'], $payload['name'], $payload['full_name'])) {
                continue;
            }

            $repository = Repository::updateOrCreate(
                ['github_id' => $payload['id']],
                [
                    'name' => $payload['name'],
                    'full_name' => $payload['full_name'],
                    'description' => $payload['description'] ?? null,
                    'default_branch' => $payload['default_branch'] ?? null,
                    'is_private' => (bool) ($payload['private'] ?? false),
                    'topics' => $payload['topics'] ?? [],
                ]
            );

            SyncRepoJob::dispatch($repository);
            $count++;
        }

        $this->info("Upserted {$count} repositories and dispatched SyncRepoJob for each.");

        return self::SUCCESS;
    }
}
