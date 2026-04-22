<?php

namespace App\Services\Billing\Gateways;

use App\Contracts\Billing\InvoiceWhatsAppGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StubInvoiceWhatsAppGateway implements InvoiceWhatsAppGateway
{
    public function send(array $payload): array
    {
        $messageId = 'wa-stub-'.Str::uuid()->toString();

        Log::channel(config('logging.default'))->info('Stub invoice WhatsApp dispatch queued.', [
            ...$payload,
            'message_id' => $messageId,
        ]);

        return [
            'driver' => 'stub',
            'status' => 'queued',
            'message_id' => $messageId,
        ];
    }
}
