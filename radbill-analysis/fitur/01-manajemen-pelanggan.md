# Fitur 01: Manajemen Pelanggan

## Database Migration

```php
// database/migrations/create_customers_table.php
Schema::create('customers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('username')->unique();
    $table->string('email')->nullable()->unique();
    $table->string('phone', 20)->nullable();
    $table->text('address')->nullable();
    $table->string('identity_number', 50)->nullable(); // NIK/KTP
    $table->enum('status', ['active', 'suspended', 'inactive'])->default('active');
    $table->foreignId('package_id')->nullable()->constrained();
    $table->foreignId('reseller_id')->nullable()->constrained('users');
    $table->date('active_date')->nullable();
    $table->date('expired_date')->nullable();
    $table->string('nas_port')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

## Model

```php
// app/Models/Customer.php
class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'username', 'email', 'phone', 'address',
        'identity_number', 'status', 'package_id',
        'reseller_id', 'active_date', 'expired_date',
        'nas_port', 'ip_address',
    ];

    protected $casts = [
        'active_date' => 'date',
        'expired_date' => 'date',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function suspend(): void
    {
        $this->update(['status' => 'suspended']);
        // Disconnect from MikroTik
        app(MikrotikService::class)->disconnectUser($this->username);
    }

    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }
}
```

## Controller

```php
// app/Http/Controllers/CustomerController.php
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::with(['package', 'reseller'])
            ->when($request->search, fn($q) => $q
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('username', 'like', "%{$request->search}%")
                ->orWhere('phone', 'like', "%{$request->search}%")
            )
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->package_id, fn($q) => $q->where('package_id', $request->package_id))
            ->latest()
            ->paginate(20);

        return view('customers.index', compact('customers'));
    }

    public function store(CustomerRequest $request)
    {
        $customer = Customer::create($request->validated());

        // Create PPPoE/Hotspot user on MikroTik
        app(MikrotikService::class)->createUser($customer);

        // Create initial invoice
        app(InvoiceService::class)->createForCustomer($customer);

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Pelanggan berhasil ditambahkan');
    }

    public function suspend(Customer $customer)
    {
        $customer->suspend();
        return back()->with('success', 'Pelanggan berhasil di-suspend');
    }

    public function activate(Customer $customer)
    {
        $customer->activate();
        app(MikrotikService::class)->activateUser($customer);
        return back()->with('success', 'Pelanggan berhasil diaktifkan');
    }
}
```

## Request Validation

```php
// app/Http/Requests/CustomerRequest.php
class CustomerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'            => 'required|string|max:255',
            'username'        => ['required', 'string', 'max:50', Rule::unique('customers')->ignore($this->customer)],
            'email'           => ['nullable', 'email', Rule::unique('customers')->ignore($this->customer)],
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string',
            'identity_number' => 'nullable|string|max:50',
            'package_id'      => 'required|exists:packages,id',
            'reseller_id'     => 'nullable|exists:users,id',
            'active_date'     => 'nullable|date',
        ];
    }
}
```
