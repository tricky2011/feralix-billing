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
        'network_location_id',
        'location_id',
        'name',
        'code',
        'host',
        'brand',
        'model',
        'pon_ports',
        'status',
        'description',
        'olt_code',
        'olt_name',
        'mgmt_ip',
        'vendor_name',
        'location_name',
        'is_active',
        'router_id',
    ];

    protected function casts(): array
    {
        return [
            'network_location_id' => 'integer',
            'location_id' => 'integer',
            'pon_ports' => 'integer',
            'is_active' => 'boolean',
            'router_id' => 'integer',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function networkLocation(): BelongsTo
    {
        return $this->belongsTo(NetworkLocation::class, 'network_location_id');
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
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

    public function odps(): HasMany
    {
        return $this->hasMany(Odp::class);
    }

    public function ponPorts(): HasMany
    {
        return $this->hasMany(PonPort::class)->orderBy('port_number');
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
                ->orWhere('host', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")
                ->orWhere('model', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhere('olt_code', 'like', "%{$search}%")
                ->orWhere('olt_name', 'like', "%{$search}%")
                ->orWhere('mgmt_ip', 'like', "%{$search}%")
                ->orWhere('vendor_name', 'like', "%{$search}%")
                ->orWhere('location_name', 'like', "%{$search}%")
                ->orWhereHas('location', fn (Builder $locationQuery) => $locationQuery->search($search))
                ->orWhereHas('networkLocation', fn (Builder $locationQuery) => $locationQuery->search($search));
        });
    }
}
