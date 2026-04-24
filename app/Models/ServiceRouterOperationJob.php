<?php

namespace App\Models;

use App\Enums\ServiceRouterOperationJobStatus;
use App\Enums\ServiceRouterOperationType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceRouterOperationJob extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'service_id',
        'service_isolation_id',
        'router_id',
        'operation_type',
        'job_status',
        'queue_name',
        'address_list_name',
        'target_address',
        'attempts',
        'started_at',
        'finished_at',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'service_id' => 'integer',
            'service_isolation_id' => 'integer',
            'router_id' => 'integer',
            'operation_type' => ServiceRouterOperationType::class,
            'job_status' => ServiceRouterOperationJobStatus::class,
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceIsolation(): BelongsTo
    {
        return $this->belongsTo(ServiceIsolation::class);
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ServiceRouterOperationLog::class, 'operation_job_id');
    }
}
