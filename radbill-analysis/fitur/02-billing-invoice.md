# Fitur 02: Billing & Invoice

## Database Migrations

```php
// invoices table
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_number')->unique();
    $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
    $table->foreignId('package_id')->constrained();
    $table->decimal('amount', 12, 2);
    $table->decimal('paid_amount', 12, 2)->default(0);
    $table->enum('status', ['unpaid', 'partial', 'paid'])->default('unpaid');
    $table->date('due_date');
    $table->date('paid_date')->nullable();
    $table->date('suspend_date')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

// payments table
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
    $table->foreignId('customer_id')->constrained();
    $table->foreignId('cashier_id')->nullable()->constrained('users');
    $table->decimal('amount', 12, 2);
    $table->enum('method', ['cash', 'transfer', 'gateway', 'voucher'])->default('cash');
    $table->string('reference')->nullable();
    $table->timestamp('paid_at');
    $table->timestamps();
});
```

## Model Invoice

```php
// app/Models/Invoice.php
class Invoice extends Model
{
    use SoftDeletes;

    protected $casts = [
        'due_date'     => 'date',
        'paid_date'    => 'date',
        'suspend_date' => 'date',
        'amount'       => 'decimal:2',
        'paid_amount'  => 'decimal:2',
    ];

    public function getRemainingAmountAttribute(): float
    {
        return $this->amount - $this->paid_amount;
    }

    public function isFullyPaid(): bool
    {
        return $this->paid_amount >= $this->amount;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->paid_amount > 0 && $this->paid_amount < $this->amount;
    }

    public function pay(float $amount, string $method = 'cash', ?User $cashier = null): Payment
    {
        $payment = $this->payments()->create([
            'customer_id' => $this->customer_id,
            'cashier_id'  => $cashier?->id,
            'amount'      => $amount,
            'method'      => $method,
            'paid_at'     => now(),
        ]);

        $this->paid_amount += $amount;

        if ($this->isFullyPaid()) {
            $this->status = 'paid';
            $this->paid_date = now();
            $this->suspend_date = null; // Reset suspend date
        } else {
            $this->status = 'partial';
        }

        $this->save();

        return $payment;
    }
}
```

## InvoiceService

```php
// app/Services/InvoiceService.php
class InvoiceService
{
    public function createForCustomer(Customer $customer): Invoice
    {
        $package = $customer->package;

        return Invoice::create([
            'invoice_number' => $this->generateInvoiceNumber(),
            'customer_id'    => $customer->id,
            'package_id'     => $package->id,
            'amount'         => $package->price,
            'due_date'       => now()->addDays(config('billing.due_days', 30)),
            'suspend_date'   => now()->addDays(config('billing.suspend_days', 35)),
        ]);
    }

    public function processPayment(Invoice $invoice, float $amount, string $method, ?User $cashier = null): void
    {
        $payment = $invoice->pay($amount, $method, $cashier);

        if ($invoice->isFullyPaid()) {
            // Reactivate customer if suspended
            if ($invoice->customer->isSuspended()) {
                $invoice->customer->activate();
            }

            // Update RADIUS expiration
            app(RadiusService::class)->updateExpiration($invoice->customer);

            // Send WhatsApp confirmation
            app(WhatsAppService::class)->sendPaymentConfirmation($invoice, $payment);

            // Generate next invoice
            $this->createForCustomer($invoice->customer);
        }
    }

    public function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Ymd') . '-';
        $last = Invoice::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('invoice_number')
            ->first();

        $sequence = $last ? intval(substr($last->invoice_number, -4)) + 1 : 1;
        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    // Jalankan via scheduler setiap hari
    public function processSuspensions(): void
    {
        Invoice::where('status', '!=', 'paid')
            ->where('suspend_date', '<=', today())
            ->with('customer')
            ->each(function (Invoice $invoice) {
                if (!$invoice->customer->isSuspended()) {
                    $invoice->customer->suspend();
                    app(WhatsAppService::class)->sendSuspendNotification($invoice->customer);
                }
            });
    }
}
```

## Command Auto-Generate Invoice & Suspend

```php
// app/Console/Commands/ProcessBilling.php
class ProcessBilling extends Command
{
    protected $signature = 'billing:process';
    protected $description = 'Generate invoices dan proses suspend pelanggan';

    public function handle(InvoiceService $invoiceService): void
    {
        $this->info('Processing billing...');
        $invoiceService->processSuspensions();
        $this->info('Billing processed successfully.');
    }
}
```

```php
// app/Console/Kernel.php
$schedule->command('billing:process')->dailyAt('00:01');
```

## PDF Invoice Download

```php
// app/Http/Controllers/InvoiceController.php
public function download(Invoice $invoice)
{
    $this->authorize('view', $invoice);

    $pdf = Pdf::loadView('invoices.pdf', compact('invoice'));
    return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
}
```

## Voucher System

```php
// vouchers table
Schema::create('vouchers', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique();
    $table->decimal('value', 12, 2);
    $table->foreignId('package_id')->nullable()->constrained();
    $table->enum('status', ['available', 'used'])->default('available');
    $table->foreignId('created_by')->constrained('users');
    $table->foreignId('used_by')->nullable()->constrained('customers');
    $table->timestamp('used_at')->nullable();
    $table->date('expired_date')->nullable();
    $table->timestamps();
});

// app/Models/Voucher.php
class Voucher extends Model
{
    public function redeem(Customer $customer): void
    {
        if ($this->status === 'used') {
            throw new \Exception('Voucher sudah digunakan');
        }

        DB::transaction(function () use ($customer) {
            // Update voucher status
            $this->update([
                'status'  => 'used',
                'used_by' => $customer->id,
                'used_at' => now(),
            ]);

            // Find unpaid invoice and apply voucher value
            $invoice = $customer->invoices()->where('status', '!=', 'paid')->latest()->first();
            if ($invoice) {
                app(InvoiceService::class)->processPayment($invoice, $this->value, 'voucher');
            }

            // Revenue dicatat DI SINI (saat ditukar), bukan saat dibuat
            Transaction::create([
                'type'       => 'voucher_redemption',
                'amount'     => $this->value,
                'reference'  => $this->code,
                'customer_id' => $customer->id,
            ]);
        });
    }
}
```
