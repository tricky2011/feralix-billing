<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nas extends Model
{
    /** @use HasFactory<\Database\Factories\NasFactory> */
    use HasFactory;

    protected $fillable = [
        'nasname',
        'shortname',
        'type',
        'ports',
        'secret',
        'server',
        'community',
        'description',
    ];

    protected $hidden = [
        'secret',
    ];

    protected function casts(): array
    {
        return [
            'ports' => 'integer',
        ];
    }

    public function scopeSearch($query, ?string $search)
    {
        if ($search === null || $search === '') {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('nasname', 'like', "%{$search}%")
              ->orWhere('shortname', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }
}
