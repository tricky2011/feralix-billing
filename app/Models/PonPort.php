<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PonPort extends Model
{
    use HasFactory;

    protected $fillable = [
        'olt_id',
        'port_number',
        'name',
        'max_capacity',
        'current_count',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'olt_id' => 'integer',
            'port_number' => 'integer',
            'max_capacity' => 'integer',
            'current_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(Olt::class);
    }

    public function getSisaAttribute(): int
    {
        return max(0, $this->max_capacity - $this->current_count);
    }

    public function getIsFullAttribute(): bool
    {
        return $this->current_count >= $this->max_capacity;
    }

    public function getIsAlmostFullAttribute(): bool
    {
        return ($this->max_capacity - $this->current_count) <= (int) ceil($this->max_capacity * 0.2);
    }

    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }
        if ($this->is_full) {
            return 'full';
        }
        if ($this->is_almost_full) {
            return 'almost_full';
        }

        return 'normal';
    }
}