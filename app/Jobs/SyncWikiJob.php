<?php

namespace App\Jobs;

use App\Models\Repository;
use App\Models\WikiPage;
use App\Services\GitHubAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class SyncWikiJob implements ShouldQueue
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
        $token = $github->installationToken();
        $cloneUrl = 'https://x-access-token:'.rawurlencode($token).'@github.com/'
            .rawurlencode($owner).'/'.rawurlencode($name).'.wiki.git';

        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.$name.'-wiki-'.uniqid('', true);

        try {
            $result = Process::timeout(600)->run([
                'git', 'clone', '--depth', '1', $cloneUrl, $dir,
            ]);

            if (! $result->successful()) {
                Log::info('Wiki clone skipped or failed', [
                    'repository' => $repo->full_name,
                    'error' => $result->errorOutput() ?: $result->output(),
                ]);

                return;
            }

            $rev = Process::run(['git', '-C', $dir, 'rev-parse', 'HEAD']);
            $sha = $rev->successful() ? trim($rev->output()) : '';

            $now = now();
            $rows = [];

            $files = File::allFiles($dir);
            foreach ($files as $file) {
                if (strtolower($file->getExtension()) !== 'md') {
                    continue;
                }

                $slug = $file->getBasename('.md');
                $content = File::get($file->getPathname());
                $title = $this->inferTitle($content, $slug);

                $rows[] = [
                    'repository_id' => $repo->id,
                    'slug' => $slug,
                    'title' => $title,
                    'content_md' => $content,
                    'sha' => $sha,
                    'synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            WikiPage::where('repository_id', $repo->id)->delete();

            foreach (array_chunk($rows, 100) as $chunk) {
                WikiPage::insert($chunk);
            }
        } finally {
            if (File::exists($dir)) {
                File::deleteDirectory($dir);
            }
        }
    }

    private function inferTitle(string $content, string $fallback): string
    {
        foreach (preg_split("/\r\n|\n|\r/", $content) as $line) {
            if (preg_match('/^\s*#\s+(.+)/', $line, $m)) {
                return trim($m[1]);
            }
        }

        return $fallback;
    }
}
