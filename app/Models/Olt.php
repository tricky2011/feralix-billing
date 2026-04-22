<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Olt extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'olt_code',
        'olt_name',
        'mgmt_ip',
        'vendor_name',
        'location_id',
        'location_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'location_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function onts(): HasMany
    {
        return $this->hasMany(Ont::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('olt_code', 'like', "%{$search}%")
                ->orWhere('olt_name', 'like', "%{$search}%")
                ->orWhere('mgmt_ip', 'like', "%{$search}%")
                ->orWhere('vendor_name', 'like', "%{$search}%")
                ->orWhere('location_name', 'like', "%{$search}%")
                ->orWhereHas('location', fn (Builder $locationQuery) => $locationQuery->search($search));
        });
    }
}
