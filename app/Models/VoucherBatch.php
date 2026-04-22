<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoucherBatch extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'reseller_id',
        'hotspot_profile_id',
        'batch_code',
        'total_vouchers',
        'total_cost',
        'generated_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'reseller_id' => 'integer',
            'hotspot_profile_id' => 'integer',
            'total_vouchers' => 'integer',
            'total_cost' => 'decimal:2',
            'generated_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function hotspotProfile(): BelongsTo
    {
        return $this->belongsTo(HotspotProfile::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(HotspotVoucher::class, 'batch_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where('batch_code', 'like', "%{$search}%");
    }
}
