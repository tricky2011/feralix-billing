<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    protected $fillable = [
        'setting_group',
        'setting_key',
        'setting_value',
        'value_type',
        'is_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public function value(): ?string
    {
        if ($this->setting_value === null) {
            return null;
        }

        return $this->is_encrypted
            ? Crypt::decryptString($this->setting_value)
            : $this->setting_value;
    }

    public static function write(string $group, string $key, ?string $value, bool $encrypted = false): self
    {
        return self::query()->updateOrCreate(
            [
                'setting_group' => $group,
                'setting_key' => $key,
            ],
            [
                'setting_value' => $encrypted && $value !== null
                    ? Crypt::encryptString($value)
                    : $value,
                'value_type' => 'string',
                'is_encrypted' => $encrypted,
            ],
        );
    }
}
