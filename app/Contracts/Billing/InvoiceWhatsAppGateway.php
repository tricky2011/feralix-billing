<?php

namespace App\Contracts\Billing;

interface InvoiceWhatsAppGateway
{
    public function send(array $payload): array;
}
