<?php

namespace App\Models;

use App\Enums\HotspotVoucherStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotspotVoucher extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'reseller_id',
        'hotspot_profile_id',
        'username',
        'password',
        'voucher_code',
        'locked_mac',
        'first_login_at',
        'expires_at',
        'bytes_used',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'batch_id' => 'integer',
            'reseller_id' => 'integer',
            'hotspot_profile_id' => 'integer',
            'password' => 'encrypted',
            'first_login_at' => 'datetime',
            'expires_at' => 'datetime',
            'bytes_used' => 'integer',
            'status' => HotspotVoucherStatus::class,
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(VoucherBatch::class, 'batch_id');
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function hotspotProfile(): BelongsTo
    {
        return $this->belongsTo(HotspotProfile::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(HotspotVoucherSession::class);
    }

    public function radiusEvents(): HasMany
    {
        return $this->hasMany(HotspotRadiusEvent::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('username', 'like', "%{$search}%")
                ->orWhere('voucher_code', 'like', "%{$search}%")
                ->orWhere('locked_mac', 'like', "%{$search}%");
        });
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if ($status === null || $status === '') {
            return $query;
        }

        return $query->where('status', $status);
    }
}
