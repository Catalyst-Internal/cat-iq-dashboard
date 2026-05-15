<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Milestone extends Model
{
    protected $fillable = [
        'repository_id',
        'github_id',
        'title',
        'state',
        'due_on',
        'open_issues',
        'closed_issues',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function progressPercent(): int
    {
        $total = $this->open_issues + $this->closed_issues;

        return $total > 0 ? (int) round(100 * $this->closed_issues / $total) : 0;
    }
}
