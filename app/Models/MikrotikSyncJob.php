<?php

namespace App\Models;

use App\Enums\MikrotikSyncJobStatus;
use App\Enums\MikrotikSyncJobType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MikrotikSyncJob extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'router_id',
        'job_type',
        'job_status',
        'started_at',
        'finished_at',
        'total_found',
        'total_new',
        'total_updated',
        'total_conflict',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'router_id' => 'integer',
            'job_type' => MikrotikSyncJobType::class,
            'job_status' => MikrotikSyncJobStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'total_found' => 'integer',
            'total_new' => 'integer',
            'total_updated' => 'integer',
            'total_conflict' => 'integer',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function vidLogs(): HasMany
    {
        return $this->hasMany(MikrotikSyncVidLog::class, 'sync_job_id');
    }
}
