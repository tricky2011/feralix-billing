<?php

namespace App\Models;

use App\Enums\ServiceIsolationStatus;
use App\Enums\ServiceIsolationTargetType;
use App\Enums\ServiceIsolationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceIsolation extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'service_id',
        'router_id',
        'invoice_id',
        'isolation_type',
        'target_type',
        'address_list_name',
        'target_subnet',
        'target_identifier',
        'target_payload',
        'redirect_url',
        'status',
        'isolated_at',
        'released_at',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'service_id' => 'integer',
            'router_id' => 'integer',
            'invoice_id' => 'integer',
            'isolation_type' => ServiceIsolationType::class,
            'target_type' => ServiceIsolationTargetType::class,
            'status' => ServiceIsolationStatus::class,
            'target_payload' => 'array',
            'isolated_at' => 'datetime',
            'released_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ServiceIsolationStatus::openValues());
    }
}
