<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrder extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'service_id',
        'router_id',
        'olt_id',
        'ont_id',
        'wo_number',
        'wo_type',
        'assigned_technician_id',
        'planned_monitor_vid',
        'planned_internet_vid',
        'planned_subnet_cidr',
        'status',
        'work_notes',
        'scheduled_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'service_id' => 'integer',
            'router_id' => 'integer',
            'olt_id' => 'integer',
            'ont_id' => 'integer',
            'assigned_technician_id' => 'integer',
            'planned_monitor_vid' => 'integer',
            'planned_internet_vid' => 'integer',
            'wo_type' => WorkOrderType::class,
            'status' => WorkOrderStatus::class,
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function ont(): BelongsTo
    {
        return $this->belongsTo(Ont::class);
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'assigned_technician_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('wo_number', 'like', "%{$search}%")
                ->orWhere('wo_type', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhere('work_notes', 'like', "%{$search}%")
                ->orWhereHas('customer', function (Builder $customerQuery) use ($search): void {
                    $customerQuery
                        ->where('customer_code', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
                })
                ->orWhereHas('service', function (Builder $serviceQuery) use ($search): void {
                    $serviceQuery->where('service_code', 'like', "%{$search}%");
                })
                ->orWhereHas('assignedTechnician', function (Builder $technicianQuery) use ($search): void {
                    $technicianQuery
                        ->where('technician_code', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
                });

            if (ctype_digit($search)) {
                $builder
                    ->orWhere('id', (int) $search)
                    ->orWhere('customer_id', (int) $search)
                    ->orWhere('service_id', (int) $search)
                    ->orWhere('router_id', (int) $search)
                    ->orWhere('assigned_technician_id', (int) $search);
            }
        });
    }
}
