<?php

namespace App\Models;

use App\Enums\TelegramLogStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramLog extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'channel',
        'target',
        'message',
        'payload',
        'status',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'payload' => 'array',
            'status' => TelegramLogStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', TelegramLogStatus::Queued->value);
    }
}
