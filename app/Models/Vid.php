<?php

namespace App\Models;

use App\Enums\VidStatus;
use App\Enums\VidType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vid extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'router_id',
        'scope_id',
        'vid',
        'vid_type',
        'subnet_cidr',
        'gateway_ip',
        'pool_start_ip',
        'pool_end_ip',
        'pool_ip_count',
        'rate_limit_mbps',
        'sync_source',
        'status',
        'customer_id',
        'service_id',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'router_id' => 'integer',
            'scope_id' => 'integer',
            'vid' => 'integer',
            'vid_type' => VidType::class,
            'pool_ip_count' => 'integer',
            'rate_limit_mbps' => 'integer',
            'status' => VidStatus::class,
            'customer_id' => 'integer',
            'service_id' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function routerScope(): BelongsTo
    {
        return $this->belongsTo(RouterScope::class, 'scope_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'vid_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            if (ctype_digit($search)) {
                $builder
                    ->where('vid', (int) $search)
                    ->orWhere('service_id', (int) $search)
                    ->orWhere('subnet_cidr', 'like', "%{$search}%")
                    ->orWhere('gateway_ip', 'like', "%{$search}%")
                    ->orWhere('pool_start_ip', 'like', "%{$search}%")
                    ->orWhere('pool_end_ip', 'like', "%{$search}%")
                    ->orWhere('sync_source', 'like', "%{$search}%");

                return;
            }

            $builder
                ->where('subnet_cidr', 'like', "%{$search}%")
                ->orWhere('gateway_ip', 'like', "%{$search}%")
                ->orWhere('pool_start_ip', 'like', "%{$search}%")
                ->orWhere('pool_end_ip', 'like', "%{$search}%")
                ->orWhere('sync_source', 'like', "%{$search}%");
        });
    }
}
