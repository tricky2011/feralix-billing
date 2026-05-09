<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\CustomerType;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_code',
        'full_name',
        'phone',
        'contact',
        'email',
        'address',
        'location_id',
        'network_location_id',
        'preferred_olt_id',
        'assigned_technician_id',
        'latitude',
        'longitude',
        'customer_type',
        'status',
        'install_date',
        'ip_count',
        'monthly_price',
        'billing_day',
        'pppoe_username',
        'pppoe_password',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'location_id' => 'integer',
            'preferred_olt_id' => 'integer',
            'assigned_technician_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'customer_type' => CustomerType::class,
            'status' => CustomerStatus::class,
            'install_date' => 'date',
        ];
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('customer_code', 'like', "%{$search}%")
                ->orWhere('full_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
                ->orWhereHas('location', fn (Builder $locationQuery) => $locationQuery->search($search))
                ->orWhereHas('preferredOlt', fn (Builder $oltQuery) => $oltQuery->search($search))
                ->orWhereHas('assignedTechnician', function (Builder $technicianQuery) use ($search): void {
                    $technicianQuery
                        ->where('technician_code', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
                })
                ->orWhereHas('services', function (Builder $serviceQuery) use ($search): void {
                    $serviceQuery
                        ->search($search)
                        ->orWhereHas('router', function (Builder $routerQuery) use ($search): void {
                            $routerQuery
                                ->where('router_code', 'like', "%{$search}%")
                                ->orWhere('router_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('olt', function (Builder $oltQuery) use ($search): void {
                            $oltQuery
                                ->where('olt_code', 'like', "%{$search}%")
                                ->orWhere('olt_name', 'like', "%{$search}%");
                        });
                });
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function preferredOlt(): BelongsTo
    {
        return $this->belongsTo(Olt::class, 'preferred_olt_id');
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'assigned_technician_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function latestService(): HasOne
    {
        return $this->hasOne(Service::class)->latestOfMany();
    }

    public function latestActiveService(): HasOne
    {
        return $this->hasOne(Service::class)
            ->ofMany(['id' => 'max'], function (Builder $query): void {
                $query
                    ->active();
            });
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
