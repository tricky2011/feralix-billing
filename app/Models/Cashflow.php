<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Cashflow extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'amount',
        'category_id',
        'description',
        'source',
        'reference_type',
        'reference_id',
        'reference_label',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'category_id' => 'integer',
            'reference_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CashflowCategory::class, 'category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function referenceLabel(): ?string
    {
        if ($this->reference_label !== null) {
            return $this->reference_label;
        }

        if ($this->reference !== null) {
            return match (true) {
                $this->reference instanceof \App\Models\Payment => sprintf(
                    'Payment #%d - Invoice %s',
                    $this->reference->id,
                    $this->reference->invoice?->invoice_number ?? 'N/A'
                ),
                $this->reference instanceof \App\Models\Invoice => sprintf(
                    'Invoice %s - %s',
                    $this->reference->invoice_number,
                    $this->reference->customer?->name ?? 'N/A'
                ),
                default => class_basename($this->reference_type) . ' #' . $this->reference_id,
            };
        }

        return null;
    }

    public function isSystemSource(): bool
    {
        return $this->source === 'system';
    }
}
