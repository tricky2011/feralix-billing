<?php

namespace App\Models;

use App\Enums\InvoicePaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Invoice extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'service_id',
        'invoice_number',
        'billing_period',
        'invoice_date',
        'due_date',
        'subtotal',
        'penalty_amount',
        'total_amount',
        'payment_status',
        'issued_at',
        'paid_at',
        'overdue_marked_at',
        'status_changed_at',
        'whatsapp_sent_at',
        'whatsapp_recipient',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'service_id' => 'integer',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'payment_status' => InvoicePaymentStatus::class,
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'overdue_marked_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'whatsapp_sent_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function serviceIsolations(): HasMany
    {
        return $this->hasMany(ServiceIsolation::class);
    }

    public function cashflowTransactions(): HasMany
    {
        return $this->hasMany(CashflowTransaction::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('invoice_number', 'like', "%{$search}%")
                ->orWhere('billing_period', 'like', "%{$search}%");
        });
    }

    public function scopePaymentStatus(Builder $query, ?string $status): Builder
    {
        if ($status === null || $status === '') {
            return $query;
        }

        return $query->where('payment_status', $status);
    }

    public function scopeOverdue(Builder $query, ?Carbon $date = null): Builder
    {
        $date ??= now()->startOfDay();

        return $query->where(function (Builder $builder) use ($date): void {
            $builder
                ->where('payment_status', InvoicePaymentStatus::Overdue->value)
                ->orWhere(function (Builder $overdueBuilder) use ($date): void {
                    $overdueBuilder
                        ->whereDate('due_date', '<', $date->toDateString())
                        ->whereIn('payment_status', [
                            InvoicePaymentStatus::Unpaid->value,
                            InvoicePaymentStatus::Issued->value,
                            InvoicePaymentStatus::PartiallyPaid->value,
                        ]);
                });
        });
    }

    public function scopeRouter(Builder $query, ?int $routerId): Builder
    {
        if ($routerId === null) {
            return $query;
        }

        return $query->whereHas('service', fn (Builder $builder) => $builder->where('router_id', $routerId));
    }

    public function amountPaid(): string
    {
        $amount = $this->payments_sum_amount_paid ?? $this->payments()->sum('amount_paid');

        return number_format((float) $amount, 2, '.', '');
    }

    public function remainingAmount(): string
    {
        $remaining = max(0, (float) $this->total_amount - (float) $this->amountPaid());

        return number_format($remaining, 2, '.', '');
    }

    public function isOverdue(): bool
    {
        if ($this->payment_status === InvoicePaymentStatus::Overdue) {
            return true;
        }

        if ($this->due_date === null || $this->payment_status?->isSettled() === true) {
            return false;
        }

        return in_array($this->payment_status?->value, InvoicePaymentStatus::payableValues(), true)
            && $this->due_date->isPast();
    }
}
