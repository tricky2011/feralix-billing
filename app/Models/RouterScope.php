<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterScope extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'router_id',
        'scope_name',
        'monitor_vid',
        'vid_start',
        'vid_end',
        'is_special',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'router_id' => 'integer',
            'monitor_vid' => 'integer',
            'vid_start' => 'integer',
            'vid_end' => 'integer',
            'is_special' => 'boolean',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('scope_name', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%");
        });
    }
}
