# Fitur 06: Payment Gateway

## Database Migration

```php
Schema::create('payment_gateways', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('provider'); // midtrans, xendit, tripay, dll
    $table->string('server_key')->nullable();
    $table->string('client_key')->nullable();
    $table->string('merchant_id')->nullable();
    $table->boolean('is_sandbox')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('gateway_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained();
    $table->foreignId('customer_id')->constrained();
    $table->string('external_id')->unique(); // ID dari gateway
    $table->string('payment_url')->nullable();
    $table->decimal('amount', 12, 2);
    $table->enum('status', ['pending', 'paid', 'failed', 'expired'])->default('pending');
    $table->string('payment_method')->nullable(); // qris, va_bca, dll
    $table->json('raw_response')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
});
```

## PaymentGatewayService (Interface)

```php
// app/Services/PaymentGateway/PaymentGatewayInterface.php
interface PaymentGatewayInterface
{
    public function createTransaction(Invoice $invoice): array;
    public function handleCallback(Request $request): GatewayTransaction;
    public function checkStatus(string $externalId): string;
}
```

## Implementasi Midtrans

```php
// app/Services/PaymentGateway/MidtransGateway.php
// composer require midtrans/midtrans-php
class MidtransGateway implements PaymentGatewayInterface
{
    public function __construct(private PaymentGateway $config)
    {
        \Midtrans\Config::$serverKey = $config->server_key;
        \Midtrans\Config::$isProduction = !$config->is_sandbox;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;
    }

    public function createTransaction(Invoice $invoice): array
    {
        $customer = $invoice->customer;

        $params = [
            'transaction_details' => [
                'order_id' => 'INV-' . $invoice->id . '-' . time(),
                'gross_amount' => (int) $invoice->remaining_amount,
            ],
            'customer_details' => [
                'first_name' => $customer->name,
                'email'      => $customer->email,
                'phone'      => $customer->phone,
            ],
            'item_details' => [
                [
                    'id'       => $invoice->package_id,
                    'price'    => (int) $invoice->remaining_amount,
                    'quantity' => 1,
                    'name'     => $invoice->package->name,
                ],
            ],
        ];

        $snapToken = \Midtrans\Snap::getSnapToken($params);

        $transaction = GatewayTransaction::create([
            'invoice_id'  => $invoice->id,
            'customer_id' => $customer->id,
            'external_id' => $params['transaction_details']['order_id'],
            'amount'      => $invoice->remaining_amount,
            'status'      => 'pending',
        ]);

        return [
            'snap_token'   => $snapToken,
            'payment_url'  => "https://app.midtrans.com/snap/v2/vtweb/{$snapToken}",
            'external_id'  => $transaction->external_id,
        ];
    }

    public function handleCallback(Request $request): GatewayTransaction
    {
        $notif = new \Midtrans\Notification();

        $transaction = GatewayTransaction::where('external_id', $notif->order_id)->firstOrFail();

        $status = match($notif->transaction_status) {
            'settlement', 'capture' => 'paid',
            'deny', 'cancel', 'reject' => 'failed',
            'expire' => 'expired',
            default => 'pending',
        };

        $transaction->update([
            'status'         => $status,
            'payment_method' => $notif->payment_type,
            'raw_response'   => $request->all(),
            'paid_at'        => $status === 'paid' ? now() : null,
        ]);

        if ($status === 'paid') {
            app(InvoiceService::class)->processPayment(
                $transaction->invoice,
                $transaction->amount,
                'gateway'
            );
        }

        return $transaction;
    }

    public function checkStatus(string $externalId): string
    {
        $status = \Midtrans\Transaction::status($externalId);
        return $status->transaction_status;
    }
}
```

## Implementasi Xendit

```php
// app/Services/PaymentGateway/XenditGateway.php
// composer require xendit/xendit-php
class XenditGateway implements PaymentGatewayInterface
{
    public function __construct(private PaymentGateway $config)
    {
        \Xendit\Xendit::setApiKey($config->server_key);
    }

    public function createTransaction(Invoice $invoice): array
    {
        $externalId = 'INV-' . $invoice->id . '-' . time();

        $params = [
            'external_id'       => $externalId,
            'amount'            => (int) $invoice->remaining_amount,
            'payer_email'       => $invoice->customer->email,
            'description'       => "Tagihan {$invoice->package->name} - {$invoice->customer->name}",
            'invoice_duration'  => 86400, // 24 jam
            'success_redirect_url' => route('client.invoices'),
            'failure_redirect_url' => route('client.invoices'),
        ];

        $response = \Xendit\Invoice::create($params);

        GatewayTransaction::create([
            'invoice_id'  => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'external_id' => $externalId,
            'payment_url' => $response['invoice_url'],
            'amount'      => $invoice->remaining_amount,
            'status'      => 'pending',
        ]);

        return [
            'payment_url' => $response['invoice_url'],
            'external_id' => $externalId,
        ];
    }

    public function handleCallback(Request $request): GatewayTransaction
    {
        // Verifikasi webhook token dari Xendit
        $callbackToken = $request->header('x-callback-token');
        if ($callbackToken !== config('services.xendit.webhook_token')) {
            abort(403, 'Invalid callback token');
        }

        $transaction = GatewayTransaction::where('external_id', $request->external_id)->firstOrFail();

        $status = match($request->status) {
            'PAID'    => 'paid',
            'EXPIRED' => 'expired',
            default   => 'pending',
        };

        $transaction->update([
            'status'       => $status,
            'raw_response' => $request->all(),
            'paid_at'      => $status === 'paid' ? now() : null,
        ]);

        if ($status === 'paid') {
            app(InvoiceService::class)->processPayment(
                $transaction->invoice,
                $transaction->amount,
                'gateway'
            );
        }

        return $transaction;
    }

    public function checkStatus(string $externalId): string
    {
        $invoice = \Xendit\Invoice::retrieve($externalId);
        return strtolower($invoice['status']);
    }
}
```

## PaymentGatewayController (Webhook)

```php
// app/Http/Controllers/WebhookController.php
class WebhookController extends Controller
{
    public function midtrans(Request $request)
    {
        app(MidtransGateway::class)->handleCallback($request);
        return response()->json(['status' => 'ok']);
    }

    public function xendit(Request $request)
    {
        app(XenditGateway::class)->handleCallback($request);
        return response()->json(['status' => 'ok']);
    }
}

// routes/web.php
Route::post('webhooks/midtrans', [WebhookController::class, 'midtrans'])->name('webhooks.midtrans');
Route::post('webhooks/xendit', [WebhookController::class, 'xendit'])->name('webhooks.xendit');
```
