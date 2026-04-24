<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ont extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $hidden = [
        'wifi_password',
    ];

    protected $fillable = [
        'olt_id',
        'ont_sn',
        'ont_name',
        'pon_port',
        'onu_id',
        'ssid_name',
        'wifi_password',
        'optical_status',
        'optical_info',
        'rx_power',
        'tx_power',
        'genieacs_device_id',
        'status',
        'last_seen_at',
        'last_inform_at',
        'genieacs_last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'olt_id' => 'integer',
            'onu_id' => 'integer',
            'rx_power' => 'decimal:2',
            'tx_power' => 'decimal:2',
            'last_seen_at' => 'datetime',
            'last_inform_at' => 'datetime',
            'genieacs_last_synced_at' => 'datetime',
        ];
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
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
                ->where('ont_sn', 'like', "%{$search}%")
                ->orWhere('ont_name', 'like', "%{$search}%")
                ->orWhere('pon_port', 'like', "%{$search}%")
                ->orWhere('ssid_name', 'like', "%{$search}%")
                ->orWhere('genieacs_device_id', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhere('optical_status', 'like', "%{$search}%")
                ->orWhere('optical_info', 'like', "%{$search}%");
        });
    }
}
