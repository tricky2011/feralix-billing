<?php

namespace App\Models;

use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    protected $fillable = [
        'package_name',
        'monthly_price',
        'ip_pool_count',
        'rate_limit_mbps',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'ip_pool_count' => 'integer',
            'rate_limit_mbps' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('package_name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
