<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessExpense extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'router_id',
        'expense_date',
        'category',
        'description',
        'amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'router_id' => 'integer',
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'created_by' => 'integer',
        ];
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
