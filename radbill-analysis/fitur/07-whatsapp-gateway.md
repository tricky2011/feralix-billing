# Fitur 07: WhatsApp Gateway

## Database Migration

```php
Schema::create('whatsapp_settings', function (Blueprint $table) {
    $table->id();
    $table->string('provider'); // fonnte, wablas, wuzapi, dll
    $table->string('api_url');
    $table->string('api_token');
    $table->string('sender_number', 20); // nomor WhatsApp pengirim
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('whatsapp_logs', function (Blueprint $table) {
    $table->id();
    $table->string('recipient', 20);
    $table->text('message');
    $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
    $table->string('provider');
    $table->json('response')->nullable();
    $table->foreignId('customer_id')->nullable()->constrained();
    $table->string('type'); // invoice, reminder, payment_confirm, suspend
    $table->timestamp('sent_at')->nullable();
    $table->timestamps();
});
```

## WhatsAppService

```php
// app/Services/WhatsAppService.php
class WhatsAppService
{
    private WhatsappSetting $config;

    public function __construct()
    {
        $this->config = WhatsappSetting::where('is_active', true)->first();
    }

    public function send(string $phone, string $message, ?Customer $customer = null, string $type = 'general'): bool
    {
        if (!$this->config) return false;

        $phone = $this->normalizePhone($phone);

        $log = WhatsappLog::create([
            'recipient'   => $phone,
            'message'     => $message,
            'status'      => 'pending',
            'provider'    => $this->config->provider,
            'customer_id' => $customer?->id,
            'type'        => $type,
        ]);

        try {
            $response = match($this->config->provider) {
                'fonnte'  => $this->sendFonnte($phone, $message),
                'wablas'  => $this->sendWablas($phone, $message),
                default   => throw new \Exception("Provider tidak didukung"),
            };

            $log->update([
                'status'    => 'sent',
                'response'  => $response,
                'sent_at'   => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'response' => ['error' => $e->getMessage()]]);
            return false;
        }
    }

    // Kirim notifikasi invoice baru
    public function sendInvoiceNotification(Invoice $invoice): void
    {
        $customer = $invoice->customer;
        if (!$customer->phone) return;

        $message = $this->renderTemplate('invoice', [
            'name'           => $customer->name,
            'invoice_number' => $invoice->invoice_number,
            'amount'         => number_format($invoice->amount, 0, ',', '.'),
            'due_date'       => $invoice->due_date->format('d/m/Y'),
            'package'        => $invoice->package->name,
        ]);

        $this->send($customer->phone, $message, $customer, 'invoice');
    }

    // Kirim pengingat jatuh tempo (H-3, H-1)
    public function sendDueDateReminder(Invoice $invoice, int $daysLeft): void
    {
        $customer = $invoice->customer;
        if (!$customer->phone) return;

        $message = $this->renderTemplate('reminder', [
            'name'      => $customer->name,
            'days_left' => $daysLeft,
            'amount'    => number_format($invoice->remaining_amount, 0, ',', '.'),
            'due_date'  => $invoice->due_date->format('d/m/Y'),
        ]);

        $this->send($customer->phone, $message, $customer, 'reminder');
    }

    // Konfirmasi pembayaran
    public function sendPaymentConfirmation(Invoice $invoice, Payment $payment): void
    {
        $customer = $invoice->customer;
        if (!$customer->phone) return;

        $message = $this->renderTemplate('payment_confirm', [
            'name'    => $customer->name,
            'amount'  => number_format($payment->amount, 0, ',', '.'),
            'method'  => $payment->method,
            'invoice' => $invoice->invoice_number,
            'status'  => $invoice->isFullyPaid() ? 'LUNAS' : 'BAYAR SEBAGIAN',
        ]);

        $this->send($customer->phone, $message, $customer, 'payment_confirm');
    }

    // Notifikasi suspend
    public function sendSuspendNotification(Customer $customer): void
    {
        if (!$customer->phone) return;

        $message = $this->renderTemplate('suspend', [
            'name' => $customer->name,
        ]);

        $this->send($customer->phone, $message, $customer, 'suspend');
    }

    private function sendFonnte(string $phone, string $message): array
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->api_token,
        ])->post('https://api.fonnte.com/send', [
            'target'  => $phone,
            'message' => $message,
        ]);

        return $response->json();
    }

    private function sendWablas(string $phone, string $message): array
    {
        $response = Http::withHeaders([
            'Authorization' => $this->config->api_token,
        ])->post($this->config->api_url . '/api/send-message', [
            'phone'   => $phone,
            'message' => $message,
        ]);

        return $response->json();
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        return $phone;
    }

    private function renderTemplate(string $type, array $vars): string
    {
        $templates = [
            'invoice' => "Halo *{name}*,\n\nTagihan internet Anda telah terbit.\n\n📋 No. Invoice: {invoice_number}\n📦 Paket: {package}\n💰 Tagihan: Rp {amount}\n📅 Jatuh Tempo: {due_date}\n\nHarap segera lakukan pembayaran sebelum jatuh tempo.\n\nTerima kasih.",
            'reminder' => "Halo *{name}*,\n\n⚠️ Pengingat! Tagihan internet Anda akan jatuh tempo dalam *{days_left} hari* lagi.\n\n💰 Sisa Tagihan: Rp {amount}\n📅 Jatuh Tempo: {due_date}\n\nSegera lakukan pembayaran untuk menghindari pemutusan layanan.",
            'payment_confirm' => "Halo *{name}*,\n\n✅ Pembayaran Anda telah diterima.\n\n💰 Jumlah: Rp {amount}\n💳 Metode: {method}\n📋 Invoice: {invoice}\n📊 Status: *{status}*\n\nTerima kasih atas pembayaran Anda.",
            'suspend' => "Halo *{name}*,\n\n🚫 Layanan internet Anda telah dinonaktifkan sementara karena belum ada pembayaran.\n\nSilakan lakukan pembayaran atau hubungi admin untuk mengaktifkan kembali layanan Anda.",
        ];

        $template = $templates[$type] ?? '';
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }

        return $template;
    }
}
```

## Job Queue untuk Kirim WA

```php
// app/Jobs/SendWhatsAppJob.php
class SendWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // retry setelah 60 detik

    public function __construct(
        private string $phone,
        private string $message,
        private ?int $customerId = null,
        private string $type = 'general'
    ) {}

    public function handle(WhatsAppService $service): void
    {
        $customer = $this->customerId ? Customer::find($this->customerId) : null;
        $service->send($this->phone, $this->message, $customer, $this->type);
    }
}
```

## Scheduler Reminder

```php
// app/Console/Commands/SendDueDateReminders.php
class SendDueDateReminders extends Command
{
    protected $signature = 'billing:send-reminders';

    public function handle(WhatsAppService $waService): void
    {
        // Kirim reminder H-3
        Invoice::where('status', '!=', 'paid')
            ->whereDate('due_date', now()->addDays(3))
            ->with('customer')
            ->each(fn($invoice) => $waService->sendDueDateReminder($invoice, 3));

        // Kirim reminder H-1
        Invoice::where('status', '!=', 'paid')
            ->whereDate('due_date', now()->addDays(1))
            ->with('customer')
            ->each(fn($invoice) => $waService->sendDueDateReminder($invoice, 1));
    }
}

// Jadwal di Kernel
$schedule->command('billing:send-reminders')->dailyAt('08:00');
```
