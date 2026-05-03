<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpPool extends Model
{
    use HasFactory;

    protected $table = 'ip_pools';

    protected $fillable = [
        'router_id',
        'pool_name',
        'vlan_id',
        'vlan_name',
        'interface',
        'dhcp_server_name',
        'ranges',
        'total_ips',
        'used_ips',
        'free_ips',
        'usage_percentage',
        'is_full',
        'is_available',
        'is_reserved',
        'availability_status',
        'is_tracked',
        'synced_at',
    ];

    protected $casts = [
        'vlan_id' => 'integer',
        'total_ips' => 'integer',
        'used_ips' => 'integer',
        'free_ips' => 'integer',
        'usage_percentage' => 'float',
        'is_full' => 'boolean',
        'is_available' => 'boolean',
        'is_reserved' => 'boolean',
        'is_tracked' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
