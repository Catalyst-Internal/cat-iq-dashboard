<?php

namespace App\Models;

use App\Enums\RoadmapItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadmapEntry extends Model
{
    protected $fillable = [
        'repository_id',
        'phase',
        'milestone_title',
        'item_text',
        'item_type',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'item_type' => RoadmapItemType::class,
        ];
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
