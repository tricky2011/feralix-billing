<?php

namespace App\Models;

use App\Enums\HotspotExpiredMode;
use App\Enums\HotspotProfileUserLock;
use App\Enums\HotspotProfileValidityMode;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class HotspotProfile extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'profile_name',
        'validity_mode',
        'validity_days',
        'data_limit_bytes',
        'price',
        'selling_price',
        'user_lock',
        'expired_mode',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'validity_mode' => HotspotProfileValidityMode::class,
            'validity_days' => 'integer',
            'data_limit_bytes' => 'integer',
            'price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'user_lock' => HotspotProfileUserLock::class,
            'expired_mode' => HotspotExpiredMode::class,
            'is_active' => 'boolean',
        ];
    }

    public function voucherBatches(): HasMany
    {
        return $this->hasMany(VoucherBatch::class);
    }

    public function hotspotVouchers(): HasMany
    {
        return $this->hasMany(HotspotVoucher::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where('profile_name', 'like', "%{$search}%");
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function usesMacLock(): bool
    {
        return $this->user_lock === HotspotProfileUserLock::Mac;
    }

    public function usesTimeExpiry(): bool
    {
        return in_array($this->expired_mode, [
            HotspotExpiredMode::Time,
            HotspotExpiredMode::TimeOrData,
        ], true);
    }

    public function usesDataExpiry(): bool
    {
        return in_array($this->expired_mode, [
            HotspotExpiredMode::Data,
            HotspotExpiredMode::TimeOrData,
        ], true);
    }

    public function resolveExpiryFromFirstLogin(DateTimeInterface $firstLoginAt): ?Carbon
    {
        if (
            ! $this->usesTimeExpiry() ||
            $this->validity_mode !== HotspotProfileValidityMode::DaysAfterFirstLogin ||
            $this->validity_days === null
        ) {
            return null;
        }

        return Carbon::instance($firstLoginAt)->copy()->addDays($this->validity_days);
    }
}
