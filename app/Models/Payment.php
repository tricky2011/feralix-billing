<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'customer_id',
        'service_id',
        'amount_paid',
        'payment_method',
        'paid_at',
        'reference_no',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'customer_id' => 'integer',
            'service_id' => 'integer',
            'amount_paid' => 'decimal:2',
            'paid_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cashflowTransaction(): HasOne
    {
        return $this->hasOne(CashflowTransaction::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('payment_method', 'like', "%{$search}%")
                ->orWhere('reference_no', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%");
        });
    }
}
