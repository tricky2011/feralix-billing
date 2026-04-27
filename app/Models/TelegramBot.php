<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramBot extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $hidden = [
        'token',
    ];

    protected $fillable = [
        'router_id',
        'bot_name',
        'token',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'router_id' => 'integer',
            'token' => 'encrypted',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TelegramGroup::class);
    }
}
