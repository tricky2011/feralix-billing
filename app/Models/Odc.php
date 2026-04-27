<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Odc extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'location_id',
        'name',
        'code',
        'latitude',
        'longitude',
        'capacity',
        'used_ports',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'location_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'capacity' => 'integer',
            'used_ports' => 'integer',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(NetworkLocation::class, 'location_id');
    }

    public function odps(): HasMany
    {
        return $this->hasMany(Odp::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('location', fn (Builder $locationQuery) => $locationQuery->search($search));
        });
    }
}
