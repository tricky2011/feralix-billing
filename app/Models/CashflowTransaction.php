<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashflowTransaction extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'invoice_id',
        'customer_id',
        'service_id',
        'router_id',
        'direction',
        'category',
        'description',
        'amount',
        'transacted_at',
        'created_by',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'invoice_id' => 'integer',
            'customer_id' => 'integer',
            'service_id' => 'integer',
            'router_id' => 'integer',
            'amount' => 'decimal:2',
            'transacted_at' => 'datetime',
            'created_by' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
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

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
