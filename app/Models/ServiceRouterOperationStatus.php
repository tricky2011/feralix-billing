<?php

namespace App\Models;

use App\Enums\ServiceAccessMode;
use App\Enums\ServiceIsolationTargetType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRouterOperationStatus extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'service_id',
        'router_id',
        'access_mode',
        'isolation_target_type',
        'pppoe_username',
        'ppp_secret_found',
        'ppp_secret_profile',
        'ppp_active_found',
        'ppp_active_address',
        'ppp_active_caller_id',
        'static_ip_address',
        'static_mac_address',
        'arp_found',
        'queue_name',
        'queue_target',
        'queue_found',
        'address_list_name',
        'address_list_target',
        'address_list_found',
        'open_isolation_id',
        'isolation_applied',
        'isolation_detected_via',
        'legacy_source',
        'remote_snapshot',
        'last_synced_at',
        'isolation_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'service_id' => 'integer',
            'router_id' => 'integer',
            'access_mode' => ServiceAccessMode::class,
            'isolation_target_type' => ServiceIsolationTargetType::class,
            'ppp_secret_found' => 'boolean',
            'ppp_active_found' => 'boolean',
            'arp_found' => 'boolean',
            'queue_found' => 'boolean',
            'address_list_found' => 'boolean',
            'open_isolation_id' => 'integer',
            'isolation_applied' => 'boolean',
            'remote_snapshot' => 'array',
            'last_synced_at' => 'datetime',
            'isolation_verified_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function openIsolation(): BelongsTo
    {
        return $this->belongsTo(ServiceIsolation::class, 'open_isolation_id');
    }
}
