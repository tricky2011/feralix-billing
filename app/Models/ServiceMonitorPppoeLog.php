<?php

namespace App\Models;

use App\Enums\MonitorPppoeStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceMonitorPppoeLog extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    public const CREATED_AT = 'observed_at';
    public const UPDATED_AT = null;

    protected $table = 'service_monitor_pppoe_logs';

    protected $fillable = [
        'service_id',
        'router_id',
        'monitor_pppoe_username',
        'previous_status',
        'status',
        'last_seen_at',
        'session_info',
        'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'service_id' => 'integer',
            'router_id' => 'integer',
            'previous_status' => MonitorPppoeStatus::class,
            'status' => MonitorPppoeStatus::class,
            'last_seen_at' => 'datetime',
            'session_info' => 'array',
            'observed_at' => 'datetime',
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
