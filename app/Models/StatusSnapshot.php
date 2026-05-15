<?php

namespace App\Models;

use App\Enums\CiState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusSnapshot extends Model
{
    protected $fillable = [
        'repository_id',
        'ci_state',
        'open_issues',
        'open_prs',
        'latest_release',
        'latest_tag',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'ci_state' => CiState::class,
            'synced_at' => 'datetime',
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
