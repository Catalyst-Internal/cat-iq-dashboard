<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Repository extends Model
{
    protected $fillable = [
        'github_id',
        'name',
        'full_name',
        'description',
        'default_branch',
        'is_private',
        'topics',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'topics' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'name';
    }

    public function statusSnapshot(): HasOne
    {
        return $this->hasOne(StatusSnapshot::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function wikiPages(): HasMany
    {
        return $this->hasMany(WikiPage::class);
    }

    public function roadmapEntries(): HasMany
    {
        return $this->hasMany(RoadmapEntry::class);
    }

    public function ownerAndRepo(): array
    {
        [$owner, $repo] = explode('/', $this->full_name, 2);

        return [$owner, $repo];
    }
}
