<?php

namespace App\Models;

use App\Enums\HotspotVoucherSessionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotspotVoucherSession extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'hotspot_voucher_id',
        'acct_session_id',
        'session_key',
        'nas_identifier',
        'nas_ip_address',
        'called_station_id',
        'calling_station_id',
        'framed_ip_address',
        'status',
        'started_at',
        'last_interim_at',
        'stopped_at',
        'session_time_seconds',
        'input_octets',
        'output_octets',
        'total_octets',
        'terminate_cause',
        'last_payload',
    ];

    protected function casts(): array
    {
        return [
            'hotspot_voucher_id' => 'integer',
            'status' => HotspotVoucherSessionStatus::class,
            'started_at' => 'datetime',
            'last_interim_at' => 'datetime',
            'stopped_at' => 'datetime',
            'session_time_seconds' => 'integer',
            'input_octets' => 'integer',
            'output_octets' => 'integer',
            'total_octets' => 'integer',
            'last_payload' => 'array',
        ];
    }

    public function hotspotVoucher(): BelongsTo
    {
        return $this->belongsTo(HotspotVoucher::class);
    }

    public function radiusEvents(): HasMany
    {
        return $this->hasMany(HotspotRadiusEvent::class, 'hotspot_voucher_session_id');
    }
}
