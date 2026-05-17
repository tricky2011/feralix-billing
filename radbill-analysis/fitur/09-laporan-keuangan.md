# Fitur 09: Laporan Keuangan & Transaksi

## Database Migration

```php
Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->enum('type', ['payment', 'voucher_redemption', 'gateway', 'refund'])->default('payment');
    $table->decimal('amount', 12, 2);
    $table->foreignId('invoice_id')->nullable()->constrained();
    $table->foreignId('customer_id')->nullable()->constrained();
    $table->foreignId('cashier_id')->nullable()->constrained('users');
    $table->string('reference')->nullable();
    $table->text('notes')->nullable();
    $table->timestamp('transacted_at');
    $table->timestamps();
});
```

## ReportController

```php
// app/Http/Controllers/Admin/ReportController.php
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date_from ? Carbon::parse($request->date_from) : now()->startOfMonth();
        $to   = $request->date_to   ? Carbon::parse($request->date_to)   : now()->endOfMonth();

        $summary = [
            'total_revenue'    => Payment::whereBetween('paid_at', [$from, $to])->sum('amount'),
            'total_payments'   => Payment::whereBetween('paid_at', [$from, $to])->count(),
            'new_customers'    => Customer::whereBetween('created_at', [$from, $to])->count(),
            'total_invoices'   => Invoice::whereBetween('created_at', [$from, $to])->count(),
            'unpaid_invoices'  => Invoice::where('status', '!=', 'paid')->count(),
            'total_suspended'  => Customer::where('status', 'suspended')->count(),
        ];

        // Pendapatan per hari (untuk chart)
        $dailyRevenue = Payment::selectRaw('DATE(paid_at) as date, SUM(amount) as total')
            ->whereBetween('paid_at', [$from, $to])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Pendapatan per paket
        $revenueByPackage = Payment::join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->join('packages', 'invoices.package_id', '=', 'packages.id')
            ->selectRaw('packages.name, SUM(payments.amount) as total, COUNT(*) as count')
            ->whereBetween('payments.paid_at', [$from, $to])
            ->groupBy('packages.id', 'packages.name')
            ->orderByDesc('total')
            ->get();

        // Laporan per kasir
        $byCashier = Payment::with('cashier')
            ->selectRaw('cashier_id, SUM(amount) as total, COUNT(*) as count')
            ->whereBetween('paid_at', [$from, $to])
            ->whereNotNull('cashier_id')
            ->groupBy('cashier_id')
            ->get();

        // Laporan per reseller
        $byReseller = Customer::join('payments', 'customers.id', '=', 'payments.customer_id')
            ->join('users', 'customers.reseller_id', '=', 'users.id')
            ->selectRaw('users.name as reseller, SUM(payments.amount) as total, COUNT(DISTINCT customers.id) as customers')
            ->whereBetween('payments.paid_at', [$from, $to])
            ->whereNotNull('customers.reseller_id')
            ->groupBy('customers.reseller_id', 'users.name')
            ->get();

        return view('admin.reports.index', compact(
            'summary', 'dailyRevenue', 'revenueByPackage', 'byCashier', 'byReseller',
            'from', 'to'
        ));
    }

    // Export ke PDF
    public function exportPdf(Request $request)
    {
        $data = $this->getReportData($request);
        $pdf = Pdf::loadView('admin.reports.pdf', $data)
            ->setPaper('A4', 'landscape');

        return $pdf->download('laporan-' . now()->format('Y-m-d') . '.pdf');
    }

    // Export ke Excel
    public function exportExcel(Request $request)
    {
        return Excel::download(
            new RevenueExport($request->date_from, $request->date_to),
            'laporan-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    private function getReportData(Request $request): array
    {
        $from = $request->date_from ? Carbon::parse($request->date_from) : now()->startOfMonth();
        $to   = $request->date_to   ? Carbon::parse($request->date_to)   : now()->endOfMonth();

        return [
            'payments' => Payment::with(['invoice.customer', 'cashier'])
                ->whereBetween('paid_at', [$from, $to])
                ->latest('paid_at')
                ->get(),
            'from' => $from,
            'to'   => $to,
        ];
    }
}
```

## Excel Export Class

```php
// app/Exports/RevenueExport.php
// composer require maatwebsite/excel
class RevenueExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private ?string $from,
        private ?string $to
    ) {}

    public function collection(): Collection
    {
        $from = $this->from ? Carbon::parse($this->from) : now()->startOfMonth();
        $to   = $this->to   ? Carbon::parse($this->to)   : now()->endOfMonth();

        return Payment::with(['invoice.customer', 'invoice.package', 'cashier'])
            ->whereBetween('paid_at', [$from, $to])
            ->latest('paid_at')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal', 'No Invoice', 'Pelanggan', 'Paket',
            'Jumlah', 'Metode', 'Kasir',
        ];
    }

    public function map($payment): array
    {
        return [
            $payment->paid_at->format('d/m/Y H:i'),
            $payment->invoice->invoice_number,
            $payment->invoice->customer->name,
            $payment->invoice->package->name,
            $payment->amount,
            strtoupper($payment->method),
            $payment->cashier?->name ?? 'Gateway',
        ];
    }
}
```

## Transaction Log

```php
// Tambahkan observer di InvoiceService agar setiap pembayaran tercatat
// app/Observers/PaymentObserver.php
class PaymentObserver
{
    public function created(Payment $payment): void
    {
        Transaction::create([
            'type'          => $payment->method === 'gateway' ? 'gateway' : 'payment',
            'amount'        => $payment->amount,
            'invoice_id'    => $payment->invoice_id,
            'customer_id'   => $payment->customer_id,
            'cashier_id'    => $payment->cashier_id,
            'reference'     => $payment->reference,
            'transacted_at' => $payment->paid_at,
        ]);
    }
}
```

## View Laporan (Chart)

```html
<!-- Chart pendapatan harian menggunakan Chart.js -->
<canvas id="revenueChart" height="100"></canvas>

<script>
const ctx = document.getElementById('revenueChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: {!! $dailyRevenue->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m')) !!},
        datasets: [{
            label: 'Pendapatan',
            data: {!! $dailyRevenue->pluck('total') !!},
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgb(54, 162, 235)',
            borderWidth: 1
        }]
    },
    options: {
        scales: {
            y: {
                ticks: {
                    callback: value => 'Rp ' + value.toLocaleString('id-ID')
                }
            }
        }
    }
});
</script>
```
