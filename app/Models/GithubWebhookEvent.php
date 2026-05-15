<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GithubWebhookEvent extends Model
{
    protected $fillable = [
        'repository_id',
        'event',
        'action',
        'github_delivery',
    ];

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
