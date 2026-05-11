<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotService extends Model
{
    /** @use HasFactory<\Database\Factories\HotspotServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'hotspot_voucher_id',
        'router_id',
        'hotspot_username',
        'mikrotik_user_id',
        'status',
        'sync_error',
    ];

    protected function casts(): array
    {
        return [
            'hotspot_voucher_id' => 'integer',
            'router_id' => 'integer',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(HotspotVoucher::class, 'hotspot_voucher_id');
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeProvisioning(Builder $query): Builder
    {
        return $query->where('status', 'provisioning');
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', 'suspended');
    }

    public function markActive(string $mikrotikUserId): void
    {
        $this->update([
            'status' => 'active',
            'mikrotik_user_id' => $mikrotikUserId,
            'sync_error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'suspended',
            'sync_error' => $error,
        ]);
    }
}
