<?php

namespace App\Models;

use App\Enums\TicketAssignmentMode;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'service_id',
        'ticket_number',
        'category',
        'priority',
        'description',
        'assigned_technician_id',
        'assignment_mode',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'service_id' => 'integer',
            'assigned_technician_id' => 'integer',
            'priority' => TicketPriority::class,
            'assignment_mode' => TicketAssignmentMode::class,
            'status' => TicketStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(Technician::class, 'assigned_technician_id');
    }

    public function telegramLogs(): HasMany
    {
        return $this->hasMany(TelegramLog::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('customer', function (Builder $customerQuery) use ($search): void {
                    $customerQuery
                        ->where('customer_code', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
                })
                ->orWhereHas('assignedTechnician', function (Builder $technicianQuery) use ($search): void {
                    $technicianQuery
                        ->where('technician_code', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
                });
        });
    }
}
