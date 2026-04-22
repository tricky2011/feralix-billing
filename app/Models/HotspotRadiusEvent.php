<?php

namespace App\Models;

use App\Enums\HotspotRadiusEventType;
use App\Enums\HotspotRadiusRejectReason;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotspotRadiusEvent extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    protected $fillable = [
        'hotspot_voucher_id',
        'hotspot_voucher_session_id',
        'event_type',
        'outcome',
        'username',
        'acct_session_id',
        'nas_identifier',
        'nas_ip_address',
        'called_station_id',
        'calling_station_id',
        'framed_ip_address',
        'session_time_seconds',
        'input_octets',
        'output_octets',
        'total_octets',
        'reason_code',
        'message',
        'occurred_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'hotspot_voucher_id' => 'integer',
            'hotspot_voucher_session_id' => 'integer',
            'event_type' => HotspotRadiusEventType::class,
            'reason_code' => HotspotRadiusRejectReason::class,
            'occurred_at' => 'datetime',
            'session_time_seconds' => 'integer',
            'input_octets' => 'integer',
            'output_octets' => 'integer',
            'total_octets' => 'integer',
            'payload' => 'array',
        ];
    }

    public function hotspotVoucher(): BelongsTo
    {
        return $this->belongsTo(HotspotVoucher::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(HotspotVoucherSession::class, 'hotspot_voucher_session_id');
    }
}
