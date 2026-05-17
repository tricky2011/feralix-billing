# Fitur 04: PPPoE & Hotspot Management

## Database Migrations

```php
// packages table (profil layanan)
Schema::create('packages', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->integer('speed_upload');   // Kbps
    $table->integer('speed_download'); // Kbps
    $table->decimal('price', 12, 2);
    $table->integer('duration_days')->default(30);
    $table->enum('type', ['pppoe', 'hotspot', 'both'])->default('pppoe');
    $table->string('mikrotik_profile')->nullable(); // nama profile di MikroTik
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// pppoe_users table
Schema::create('pppoe_users', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
    $table->foreignId('nas_id')->constrained('nas_devices');
    $table->string('username')->unique();
    $table->string('password');
    $table->string('service')->default('pppoe');
    $table->string('profile');
    $table->string('local_address', 45)->nullable();
    $table->string('remote_address', 45)->nullable();
    $table->boolean('is_enabled')->default(true);
    $table->timestamps();
});

// hotspot_users table
Schema::create('hotspot_users', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
    $table->foreignId('nas_id')->constrained('nas_devices');
    $table->string('username')->unique();
    $table->string('password');
    $table->string('profile');
    $table->string('mac_address', 17)->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->boolean('is_enabled')->default(true);
    $table->date('created_date')->nullable(); // untuk filter pencarian by date
    $table->timestamps();
});
```

## HotspotController dengan Filter Tanggal

```php
// app/Http/Controllers/HotspotController.php
class HotspotController extends Controller
{
    public function index(Request $request)
    {
        $query = HotspotUser::with(['customer', 'nasDevice'])
            ->when($request->search, fn($q) => $q
                ->where('username', 'like', "%{$request->search}%")
                ->orWhereHas('customer', fn($q) =>
                    $q->where('name', 'like', "%{$request->search}%")
                )
            )
            ->when($request->nas_id, fn($q) => $q->where('nas_id', $request->nas_id))
            ->when($request->status, fn($q) => $q->where('is_enabled', $request->status === 'active'))
            // Filter by tanggal pembuatan (fitur baru RadBill)
            ->when($request->date_from, fn($q) => $q->where('created_date', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->where('created_date', '<=', $request->date_to));

        $hotspotUsers = $query->latest()->paginate(20);
        $nasDevices = NasDevice::active()->get();

        return view('hotspot.index', compact('hotspotUsers', 'nasDevices'));
    }
}
```

## View Filter dengan Tombol Clear (Blade)

```html
<!-- resources/views/hotspot/index.blade.php -->
<form action="{{ route('hotspot.index') }}" method="GET" id="filterForm">
    <div class="row g-2 align-items-end">
        <!-- Search -->
        <div class="col-md-3">
            <label>Cari Username/Pelanggan</label>
            <input type="text" name="search" class="form-control"
                   value="{{ request('search') }}" placeholder="Cari...">
        </div>

        <!-- Filter NAS -->
        <div class="col-md-2">
            <label>NAS Device</label>
            <select name="nas_id" class="form-control">
                <option value="">Semua NAS</option>
                @foreach($nasDevices as $nas)
                    <option value="{{ $nas->id }}" {{ request('nas_id') == $nas->id ? 'selected' : '' }}>
                        {{ $nas->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Filter Tanggal (Fitur baru RadBill) -->
        <div class="col-md-2">
            <label>Dari Tanggal</label>
            <input type="date" name="date_from" class="form-control"
                   value="{{ request('date_from') }}">
        </div>
        <div class="col-md-2">
            <label>Sampai Tanggal</label>
            <input type="date" name="date_to" class="form-control"
                   value="{{ request('date_to') }}">
        </div>

        <!-- Tombol Aksi -->
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary">Filter</button>
            <!-- Tombol Clear Search (Fitur baru RadBill) -->
            <a href="{{ route('hotspot.index') }}" class="btn btn-secondary">
                Clear
            </a>
        </div>
    </div>
</form>
```

## IsolasiService (Suspend & Redirect)

```php
// app/Services/IsolasiService.php
class IsolasiService
{
    public function suspendCustomer(Customer $customer): void
    {
        // 1. Update status di database
        $customer->update(['status' => 'suspended']);

        // 2. Disconnect dari MikroTik
        $mikrotik = app(MikrotikService::class);
        $mikrotik->connect($customer->nasDevice);
        $mikrotik->suspendUser($customer);

        // 3. Redirect ke halaman isolir
        // Di MikroTik: walled garden / firewall rule
        $mikrotik->addIsolirRedirect($customer->ip_address, config('billing.isolir_url'));
    }

    public function unsuspendCustomer(Customer $customer): void
    {
        // 1. Update status
        $customer->update(['status' => 'active']);

        // 2. Reaktivasi di MikroTik
        $mikrotik = app(MikrotikService::class);
        $mikrotik->connect($customer->nasDevice);
        $mikrotik->activateUser($customer);

        // 3. Hapus redirect isolir
        $mikrotik->removeIsolirRedirect($customer->ip_address);
    }
}
```

## Halaman Isolir (Port 8087)

```php
// routes/web.php - Port 8087 khusus isolir
Route::get('/isolir', function () {
    $ip = request()->ip();
    $customer = Customer::where('ip_address', $ip)
                        ->where('status', 'suspended')
                        ->with('invoices')
                        ->first();

    return view('isolir', compact('customer'));
})->name('isolir');
```

```html
<!-- resources/views/isolir.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Layanan Diblokir</title>
</head>
<body>
    <div class="isolir-container">
        <h1>⚠️ Layanan Internet Anda Diblokir</h1>
        @if($customer)
            <p>Halo <strong>{{ $customer->name }}</strong>,</p>
            <p>Layanan internet Anda diblokir karena belum melakukan pembayaran.</p>

            @if($unpaidInvoice = $customer->invoices->where('status', '!=', 'paid')->first())
                <div class="invoice-info">
                    <p>Tagihan: <strong>Rp {{ number_format($unpaidInvoice->remaining_amount) }}</strong></p>
                    <p>Jatuh Tempo: {{ $unpaidInvoice->due_date->format('d/m/Y') }}</p>
                </div>
            @endif

            <p>Hubungi admin atau lakukan pembayaran untuk mengaktifkan kembali layanan Anda.</p>
        @endif
    </div>
</body>
</html>
```
