<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpPoolSnapshot extends Model
{
    protected $fillable = [
        'router_id', 'pool_name', 'vlan_id', 'vlan_name', 'interface',
        'dhcp_server_name', 'primary_range', 'total_ips', 'used_ips',
        'free_ips', 'usage_percentage', 'is_full', 'is_available',
        'is_reserved', 'availability_status', 'is_tracked', 'synced_at',
        'reserved_by_customer_id',
    ];

    protected function casts(): array
    {
        return [
            'vlan_id'                 => 'integer',
            'total_ips'               => 'integer',
            'used_ips'                => 'integer',
            'free_ips'                => 'integer',
            'usage_percentage'       => 'float',
            'is_full'                 => 'boolean',
            'is_available'            => 'boolean',
            'is_reserved'             => 'boolean',
            'is_tracked'              => 'boolean',
            'synced_at'               => 'datetime',
            'reserved_by_customer_id' => 'integer',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function needsRefresh(int $minutes = 5): bool
    {
        return $this->synced_at === null || $this->synced_at->diffInMinutes(now()) >= $minutes;
    }
}