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
        'api_password',
        'acs_password',
    ];

    protected $fillable = [
        'name',
        'host',
        'timeout',
        'use_ssl',
        'status',
        'router_code',
        'router_name',
        'router_role',
        'mgmt_ip',
        'api_port',
        'api_username',
        'api_password',
        'ros_version',
        'rest_port',
        'use_rest_api',
        'rest_tls',
        'acs_inform_url',
        'acs_nbi_url',
        'acs_username',
        'acs_password',
        'description',
        'location_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'api_port' => 'integer',
            'timeout' => 'integer',
            'use_ssl' => 'boolean',
            'api_password' => 'encrypted',
            'acs_password' => 'encrypted',
            'is_active' => 'boolean',
            'rest_port' => 'integer',
            'use_rest_api' => 'boolean',
            'rest_tls' => 'boolean',
        ];
    }

    public function getNameAttribute(?string $value): ?string
    {
        return $this->normalizeString($value)
            ?? $this->normalizeString($this->attributes['router_name'] ?? null);
    }

    public function setNameAttribute(?string $value): void
    {
        $normalized = $this->normalizeString($value);

        $this->attributes['name'] = $normalized;
        $this->attributes['router_name'] = $normalized;
    }

    public function getRouterNameAttribute(?string $value): ?string
    {
        return $this->normalizeString($value)
            ?? $this->normalizeString($this->attributes['name'] ?? null);
    }

    public function setRouterNameAttribute(?string $value): void
    {
        $normalized = $this->normalizeString($value);

        $this->attributes['router_name'] = $normalized;
        $this->attributes['name'] = $normalized;
    }

    public function getHostAttribute(?string $value): ?string
    {
        return $this->normalizeString($value)
            ?? $this->normalizeString($this->attributes['mgmt_ip'] ?? null);
    }

    public function setHostAttribute(?string $value): void
    {
        $normalized = $this->normalizeString($value);

        $this->attributes['host'] = $normalized;
        $this->attributes['mgmt_ip'] = $normalized;
    }

    public function getMgmtIpAttribute(?string $value): ?string
    {
        return $this->normalizeString($value)
            ?? $this->normalizeString($this->attributes['host'] ?? null);
    }

    public function setMgmtIpAttribute(?string $value): void
    {
        $normalized = $this->normalizeString($value);

        $this->attributes['mgmt_ip'] = $normalized;
        $this->attributes['host'] = $normalized;
    }

    public function getStatusAttribute(?string $value): string
    {
        $normalized = strtolower((string) $this->normalizeString($value));

        if (in_array($normalized, ['active', 'inactive'], true)) {
            return $normalized;
        }

        if (array_key_exists('is_active', $this->attributes)) {
            return (bool) $this->attributes['is_active'] ? 'active' : 'inactive';
        }

        return 'inactive';
    }

    public function setStatusAttribute(?string $value): void
    {
        $normalized = strtolower((string) $this->normalizeString($value));
        $status = $normalized === 'inactive' ? 'inactive' : 'active';

        $this->attributes['status'] = $status;
        $this->attributes['is_active'] = $status === 'active';
    }

    public function getIsActiveAttribute(mixed $value): bool
    {
        if ($value !== null) {
            return (bool) $value;
        }

        return ($this->attributes['status'] ?? 'inactive') === 'active';
    }

    public function setIsActiveAttribute(mixed $value): void
    {
        $isActive = (bool) $value;

        $this->attributes['is_active'] = $isActive;
        $this->attributes['status'] = $isActive ? 'active' : 'inactive';
    }

    public function getTimeoutAttribute(mixed $value): int
    {
        $timeout = is_numeric($value) ? (int) $value : 5;

        return $timeout > 0 ? $timeout : 5;
    }

    public function getHasApiPasswordAttribute(): bool
    {
        $raw = $this->getRawOriginal('api_password');

        if ($raw === null) {
            return false;
        }

        if (is_string($raw)) {
            return trim($raw) !== '';
        }

        return true;
    }

    public function getHasAcsPasswordAttribute(): bool
    {
        $raw = $this->getRawOriginal('acs_password');

        if ($raw === null) {
            return false;
        }

        if (is_string($raw)) {
            return trim($raw) !== '';
        }

        return true;
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

    public function hotspotServices(): HasMany
    {
        return $this->hasMany(HotspotService::class);
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
        return $this->belongsToMany(User::class, 'user_router')
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
                ->where('name', 'like', "%{$search}%")
                ->orWhere('host', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhere('router_code', 'like', "%{$search}%")
                ->orWhere('router_name', 'like', "%{$search}%")
                ->orWhere('router_role', 'like', "%{$search}%")
                ->orWhere('mgmt_ip', 'like', "%{$search}%")
                ->orWhere('location_name', 'like', "%{$search}%");
        });
    }

    private function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
