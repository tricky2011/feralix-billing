<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Router extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $hidden = [
        'api_username',
        'api_password',
    ];

    protected $fillable = [
        'router_code',
        'router_name',
        'router_role',
        'mgmt_ip',
        'api_port',
        'api_username',
        'api_password',
        'location_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'api_port' => 'integer',
            'api_password' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(RouterScope::class);
    }

    public function vids(): HasMany
    {
        return $this->hasMany(Vid::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function serviceIsolations(): HasMany
    {
        return $this->hasMany(ServiceIsolation::class);
    }

    public function serviceRouterOperationStatuses(): HasMany
    {
        return $this->hasMany(ServiceRouterOperationStatus::class);
    }

    public function dashboardUsers(): HasMany
    {
        return $this->hasMany(User::class, 'dashboard_active_router_id');
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_router_assignments')
            ->withTimestamps();
    }

    public function businessExpenses(): HasMany
    {
        return $this->hasMany(BusinessExpense::class);
    }

    public function mikrotikSyncJobs(): HasMany
    {
        return $this->hasMany(MikrotikSyncJob::class);
    }

    public function mikrotikSyncVidLogs(): HasMany
    {
        return $this->hasMany(MikrotikSyncVidLog::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('router_code', 'like', "%{$search}%")
                ->orWhere('router_name', 'like', "%{$search}%")
                ->orWhere('router_role', 'like', "%{$search}%")
                ->orWhere('mgmt_ip', 'like', "%{$search}%")
                ->orWhere('location_name', 'like', "%{$search}%");
        });
    }
}
