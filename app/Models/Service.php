<?php

namespace App\Models;

use App\Enums\ServiceAccessMode;
use App\Enums\ServiceBillingStatus;
use App\Enums\ServiceIsolationMethod;
use App\Enums\ServiceIsolationTargetType;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    use SoftDeletes;

    protected $hidden = [
        'monitor_pppoe_password',
        'pppoe_password',
    ];

    protected $fillable = [
        'customer_id',
        'package_id',
        'router_id',
        'olt_id',
        'ont_id',
        'vid_id',
        'service_code',
        'monitor_vid',
        'monitor_pppoe_username',
        'monitor_pppoe_password',
        'internet_vid',
        'subnet_cidr',
        'gateway_ip',
        'dhcp_pool_start',
        'dhcp_pool_end',
        'ip_pool_count',
        'rate_limit_mbps',
        'access_mode',
        'pppoe_username',
        'pppoe_password',
        'pppoe_isolation_profile',
        'static_ip_address',
        'static_mac_address',
        'static_queue_name',
        'legacy_secret_migrated_at',
        'isolation_method',
        'address_list_name',
        'billing_status',
        'network_status',
        'overall_status',
        'activation_date',
        'notes',
        'monthly_price',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'package_id' => 'integer',
            'router_id' => 'integer',
            'olt_id' => 'integer',
            'ont_id' => 'integer',
            'vid_id' => 'integer',
            'monitor_vid' => 'integer',
            'internet_vid' => 'integer',
            'ip_pool_count' => 'integer',
            'rate_limit_mbps' => 'integer',
            'access_mode' => ServiceAccessMode::class,
            'isolation_method' => ServiceIsolationMethod::class,
            'billing_status' => ServiceBillingStatus::class,
            'network_status' => ServiceNetworkStatus::class,
            'overall_status' => ServiceOverallStatus::class,
            'activation_date' => 'date',
            'monthly_price' => 'decimal:2',
            'legacy_secret_migrated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
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

    public function vid(): BelongsTo
    {
        return $this->belongsTo(Vid::class);
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

    public function serviceIsolations(): HasMany
    {
        return $this->hasMany(ServiceIsolation::class);
    }

    public function pppoeMonitorStatus(): HasOne
    {
        return $this->hasOne(ServiceMonitorPppoeStatus::class);
    }

    public function pppoeMonitorLogs(): HasMany
    {
        return $this->hasMany(ServiceMonitorPppoeLog::class);
    }

    public function routerOperationStatus(): HasOne
    {
        return $this->hasOne(ServiceRouterOperationStatus::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('service_code', 'like', "%{$search}%")
                ->orWhere('monitor_pppoe_username', 'like', "%{$search}%")
                ->orWhere('pppoe_username', 'like', "%{$search}%")
                ->orWhere('subnet_cidr', 'like', "%{$search}%")
                ->orWhere('static_ip_address', 'like', "%{$search}%")
                ->orWhere('static_mac_address', 'like', "%{$search}%")
                ->orWhere('gateway_ip', 'like', "%{$search}%")
                ->orWhere('dhcp_pool_start', 'like', "%{$search}%")
                ->orWhere('dhcp_pool_end', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%");

            if (ctype_digit($search)) {
                $builder
                    ->orWhere('monitor_vid', (int) $search)
                    ->orWhere('internet_vid', (int) $search);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('overall_status', ServiceOverallStatus::resourceActiveValues());
    }

    public function usesDedicatedResources(): bool
    {
        return ($this->overall_status instanceof ServiceOverallStatus)
            && $this->overall_status->usesDedicatedResources();
    }

    public function isolationTargetSubnet(): ?string
    {
        if ($this->vid !== null) {
            return $this->vid->resolveNetworkCidr();
        }

        $targetSubnet = $this->subnet_cidr;

        return $targetSubnet !== null && $targetSubnet !== ''
            ? $targetSubnet
            : null;
    }

    public function operationalPppoeUsername(): ?string
    {
        $username = trim((string) ($this->pppoe_username ?? ''));

        return $username !== ''
            ? strtolower($username)
            : null;
    }

    public function operationalStaticIpAddress(): ?string
    {
        $address = trim((string) ($this->static_ip_address ?? ''));

        return $address !== ''
            ? $address
            : null;
    }

    public function resolvedAccessMode(): ServiceAccessMode
    {
        // Explicit VLAN always wins — pppoe_username may be filled for monitoring/remote
        if ($this->access_mode === ServiceAccessMode::Vlan ||
            $this->access_mode === ServiceAccessMode::Vlan->value) {
            return ServiceAccessMode::Vlan;
        }

        if ($this->access_mode instanceof ServiceAccessMode) {
            return $this->access_mode;
        }

        // Legacy fallback: only runs when access_mode is truly not set (null)
        if ($this->pppoe_username !== null && $this->pppoe_username !== '') {
            return ServiceAccessMode::Pppoe;
        }

        if ($this->static_ip_address !== null && $this->static_ip_address !== '') {
            return ServiceAccessMode::Static;
        }

        return ServiceAccessMode::Vlan;
    }

    public function resolvedIsolationTargetType(): ServiceIsolationTargetType
    {
        if ($this->resolvedAccessMode() === ServiceAccessMode::Pppoe) {
            return ServiceIsolationTargetType::Pppoe;
        }

        if (
            $this->resolvedAccessMode() === ServiceAccessMode::Static ||
            $this->isolation_method === ServiceIsolationMethod::Queue
        ) {
            return ServiceIsolationTargetType::Static;
        }

        return ServiceIsolationTargetType::Subnet;
    }
}
