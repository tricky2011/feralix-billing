<?php

namespace App\Models;

use App\Enums\ServiceRouterOperationLogAction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRouterOperationLog extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'operation_job_id',
        'service_id',
        'service_isolation_id',
        'router_id',
        'action_type',
        'address_list_name',
        'target_address',
        'router_item_id',
        'context',
        'message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'operation_job_id' => 'integer',
            'service_id' => 'integer',
            'service_isolation_id' => 'integer',
            'router_id' => 'integer',
            'action_type' => ServiceRouterOperationLogAction::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function operationJob(): BelongsTo
    {
        return $this->belongsTo(ServiceRouterOperationJob::class, 'operation_job_id');
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
}
