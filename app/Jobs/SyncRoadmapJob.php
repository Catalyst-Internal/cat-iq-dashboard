<?php

namespace App\Jobs;

use App\Enums\RoadmapItemType;
use App\Models\Repository;
use App\Models\RoadmapEntry;
use App\Services\GitHubAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncRoadmapJob implements ShouldQueue
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
        $ref = $repo->default_branch;

        $markdown = $github->getRawContent($owner, $name, 'ROADMAP.md', $ref);

        if ($markdown === null) {
            Log::info('ROADMAP.md not found; skipping roadmap sync', ['repository' => $repo->full_name]);

            return;
        }

        $entries = $this->parseRoadmap($markdown);
        $now = now();

        RoadmapEntry::where('repository_id', $repo->id)->delete();

        $rows = [];
        $order = 0;
        foreach ($entries as $entry) {
            $rows[] = [
                'repository_id' => $repo->id,
                'phase' => $entry['phase'],
                'milestone_title' => $entry['milestone_title'],
                'item_text' => $entry['item_text'],
                'item_type' => $entry['item_type']?->value,
                'sort_order' => $order++,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            RoadmapEntry::insert($chunk);
        }
    }

    /**
     * @return list<array{phase: string, milestone_title: ?string, item_text: string, item_type: ?RoadmapItemType}>
     */
    private function parseRoadmap(string $markdown): array
    {
        $phase = 'Uncategorized';
        $milestone = null;
        $out = [];

        foreach (preg_split("/\r\n|\n|\r/", $markdown) as $line) {
            $trim = rtrim($line);
            if (preg_match('/^##\s+(.+)/', $trim, $m)) {
                $phase = trim($m[1]);
                $milestone = null;

                continue;
            }
            if (preg_match('/^###\s+(.+)/', $trim, $m)) {
                $milestone = trim($m[1]);

                continue;
            }
            if (preg_match('/^-\s+\[feature\]\s+(.+)/i', $trim, $m)) {
                $out[] = [
                    'phase' => $phase,
                    'milestone_title' => $milestone,
                    'item_text' => trim($m[1]),
                    'item_type' => RoadmapItemType::Feature,
                ];

                continue;
            }
            if (preg_match('/^-\s+\[patch\]\s+(.+)/i', $trim, $m)) {
                $out[] = [
                    'phase' => $phase,
                    'milestone_title' => $milestone,
                    'item_text' => trim($m[1]),
                    'item_type' => RoadmapItemType::Patch,
                ];

                continue;
            }
            if (preg_match('/^-\s+(.+)/', $trim, $m)) {
                $out[] = [
                    'phase' => $phase,
                    'milestone_title' => $milestone,
                    'item_text' => trim($m[1]),
                    'item_type' => null,
                ];
            }
        }

        return $out;
    }
}
