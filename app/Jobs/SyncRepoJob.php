<?php

namespace App\Jobs;

use App\Models\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncRepoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Repository $repository
    ) {}

    public function handle(): void
    {
        SyncStatusJob::dispatch($this->repository);
        SyncWikiJob::dispatch($this->repository);
        SyncRoadmapJob::dispatch($this->repository);
    }
}
