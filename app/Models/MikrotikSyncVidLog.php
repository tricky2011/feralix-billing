<?php

namespace App\Models;

use App\Enums\MikrotikSyncVidActionType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MikrotikSyncVidLog extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'mikrotik_sync_vid_logs';

    protected $fillable = [
        'sync_job_id',
        'router_id',
        'vid',
        'action_type',
        'remote_vlan_name',
        'remote_subnet_cidr',
        'remote_pool_range',
        'local_status_before',
        'local_status_after',
        'conflict_flag',
        'message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'sync_job_id' => 'integer',
            'router_id' => 'integer',
            'vid' => 'integer',
            'action_type' => MikrotikSyncVidActionType::class,
            'conflict_flag' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function syncJob(): BelongsTo
    {
        return $this->belongsTo(MikrotikSyncJob::class, 'sync_job_id');
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
