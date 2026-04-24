<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'setting_group',
        'setting_key',
        'setting_value',
        'value_type',
        'is_public',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }
}
