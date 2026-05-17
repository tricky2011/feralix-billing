# Fitur 05: Multi-Role Portal

## Database Migration

```php
// Tambah role ke users table
Schema::table('users', function (Blueprint $table) {
    $table->enum('role', ['admin', 'reseller', 'cashier', 'customer'])->default('customer');
    $table->foreignId('reseller_id')->nullable()->constrained('users'); // untuk cashier/customer di bawah reseller
});

// resellers table
Schema::create('resellers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('company_name')->nullable();
    $table->decimal('balance', 12, 2)->default(0);
    $table->decimal('commission_rate', 5, 2)->default(0); // persentase komisi
    $table->timestamps();
});
```

## Middleware Role

```php
// app/Http/Middleware/RoleMiddleware.php
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!in_array(auth()->user()?->role, $roles)) {
            abort(403, 'Akses tidak diizinkan');
        }
        return $next($request);
    }
}

// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['role' => RoleMiddleware::class]);
})
```

## Routes per Role

```php
// routes/web.php

// Admin routes
Route::prefix('admin')->middleware(['auth', 'role:admin'])->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::resource('customers', CustomerController::class);
    Route::resource('packages', PackageController::class);
    Route::resource('nas', NasController::class);
    Route::post('nas/bulk-update', [NasController::class, 'bulkUpdate'])->name('nas.bulk-update');
    Route::resource('invoices', InvoiceController::class);
    Route::resource('vouchers', VoucherController::class);
    Route::resource('resellers', ResellerController::class);
    Route::resource('cashiers', CashierController::class);
    Route::get('reports', [ReportController::class, 'index'])->name('reports');
    Route::get('monitoring', [MonitoringController::class, 'index'])->name('monitoring');
    Route::get('license', [LicenseController::class, 'index'])->name('license'); // Manajemen lisensi terintegrasi
});

// Reseller routes
Route::prefix('reseller')->middleware(['auth', 'role:reseller'])->name('reseller.')->group(function () {
    Route::get('/dashboard', [ResellerDashboardController::class, 'index'])->name('dashboard');
    Route::resource('customers', ResellerCustomerController::class);
    Route::get('invoices', [ResellerInvoiceController::class, 'index'])->name('invoices');
    Route::get('reports', [ResellerReportController::class, 'index'])->name('reports');
});

// Cashier routes
Route::prefix('cashier')->middleware(['auth', 'role:cashier'])->name('cashier.')->group(function () {
    Route::get('/dashboard', [CashierDashboardController::class, 'index'])->name('dashboard');
    Route::get('customers', [CashierCustomerController::class, 'index'])->name('customers');
    Route::post('payments', [CashierPaymentController::class, 'store'])->name('payments.store');
    Route::get('invoices/{invoice}/receipt', [CashierPaymentController::class, 'receipt'])->name('receipt');
});

// Client routes (pelanggan)
Route::prefix('client')->middleware(['auth', 'role:customer'])->name('client.')->group(function () {
    Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');
    Route::get('invoices', [ClientInvoiceController::class, 'index'])->name('invoices');
    Route::get('invoices/{invoice}/download', [ClientInvoiceController::class, 'download'])->name('invoices.download');
    Route::get('service', [ClientServiceController::class, 'index'])->name('service');
});
```

## Admin Dashboard Controller

```php
// app/Http/Controllers/Admin/AdminDashboardController.php
class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_customers'    => Customer::count(),
            'active_customers'   => Customer::where('status', 'active')->count(),
            'suspended_customers' => Customer::where('status', 'suspended')->count(),
            'total_revenue_month' => Payment::whereMonth('paid_at', now()->month)->sum('amount'),
            'unpaid_invoices'    => Invoice::where('status', '!=', 'paid')->count(),
            'active_sessions'    => $this->getActiveSessions(),
        ];

        $recentPayments = Payment::with(['invoice.customer', 'cashier'])
            ->latest('paid_at')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentPayments'));
    }

    private function getActiveSessions(): int
    {
        return cache()->remember('active_sessions', 60, function () {
            $total = 0;
            foreach (NasDevice::active()->get() as $nas) {
                try {
                    $sessions = app(MikrotikService::class)->getActivePppoeSessions($nas);
                    $total += count($sessions);
                } catch (\Exception) {}
            }
            return $total;
        });
    }
}
```

## Cashier Dashboard (Optimized)

```php
// app/Http/Controllers/Cashier/CashierDashboardController.php
// Dashboard kasir dioptimasi - hanya load data yang relevan (bukan semua data)
class CashierDashboardController extends Controller
{
    public function index()
    {
        // Hanya ambil tagihan hari ini dan yang jatuh tempo
        $todayPayments = Payment::where('cashier_id', auth()->id())
            ->whereDate('paid_at', today())
            ->sum('amount');

        $pendingInvoices = Invoice::where('status', '!=', 'paid')
            ->where('due_date', '<=', now()->addDays(7))
            ->with('customer')
            ->limit(20) // Batasi query agar cepat
            ->get();

        return view('cashier.dashboard', compact('todayPayments', 'pendingInvoices'));
    }
}
```

## Client Portal - Invoice Download

```php
// app/Http/Controllers/Client/ClientInvoiceController.php
class ClientInvoiceController extends Controller
{
    public function index()
    {
        $invoices = auth()->user()->customer->invoices()
            ->latest()
            ->paginate(10);

        return view('client.invoices.index', compact('invoices'));
    }

    // Fitur download invoice di portal client
    public function download(Invoice $invoice)
    {
        // Pastikan invoice milik pelanggan yang login
        if ($invoice->customer_id !== auth()->user()->customer->id) {
            abort(403);
        }

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice'));
        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }
}
```
