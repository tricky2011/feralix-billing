<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Odp extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'odc_id',
        'olt_id',
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
            'odc_id' => 'integer',
            'olt_id' => 'integer',
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

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function odc(): BelongsTo
    {
        return $this->belongsTo(Odc::class);
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
                ->orWhereHas('location', fn (Builder $locationQuery) => $locationQuery->search($search))
                ->orWhereHas('olt', fn (Builder $oltQuery) => $oltQuery->search($search))
                ->orWhereHas('odc', fn (Builder $odcQuery) => $odcQuery->search($search));
        });
    }
}
