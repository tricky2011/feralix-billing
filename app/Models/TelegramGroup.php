<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramGroup extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'telegram_bot_id',
        'router_id',
        'group_name',
        'chat_id',
        'group_type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'telegram_bot_id' => 'integer',
            'router_id' => 'integer',
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class, 'telegram_bot_id');
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
