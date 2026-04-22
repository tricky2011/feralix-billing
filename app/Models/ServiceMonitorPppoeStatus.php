<?php

namespace App\Models;

use App\Enums\MonitorPppoeStatus;
use App\Enums\ServiceOverallStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceMonitorPppoeStatus extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $table = 'service_monitor_pppoe_statuses';

    protected $fillable = [
        'service_id',
        'router_id',
        'monitor_pppoe_username',
        'status',
        'base_overall_status',
        'last_seen_at',
        'last_synced_at',
        'last_status_changed_at',
        'session_info',
    ];

    protected function casts(): array
    {
        return [
            'service_id' => 'integer',
            'router_id' => 'integer',
            'status' => MonitorPppoeStatus::class,
            'base_overall_status' => ServiceOverallStatus::class,
            'last_seen_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_status_changed_at' => 'datetime',
            'session_info' => 'array',
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
}
