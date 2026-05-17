# Fitur 03: Manajemen NAS / Router MikroTik

## Database Migration

```php
Schema::create('nas_devices', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('ip_address', 45);
    $table->integer('api_port')->default(8728);
    $table->string('username');
    $table->string('password');
    $table->enum('type', ['pppoe', 'hotspot', 'both'])->default('both');
    $table->enum('vpn_type', ['none', 'wireguard', 'l2tp'])->default('none');
    $table->string('vpn_endpoint')->nullable();
    $table->string('vpn_public_key')->nullable(); // WireGuard
    $table->string('vpn_private_key')->nullable(); // WireGuard
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_seen_at')->nullable();
    $table->timestamps();
});
```

## Model NasDevice

```php
// app/Models/NasDevice.php
class NasDevice extends Model
{
    protected $casts = [
        'is_active'    => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = ['password', 'vpn_private_key'];

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) < 5;
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'nas_id');
    }
}
```

## MikrotikService

```php
// app/Services/MikrotikService.php
// Menggunakan library: evilfreelancer/routeros-api-php atau similar

class MikrotikService
{
    private RouterosClient $client;

    public function connect(NasDevice $nas): void
    {
        $this->client = new RouterosClient([
            'host'     => $nas->ip_address,
            'user'     => $nas->username,
            'pass'     => $nas->password,
            'port'     => $nas->api_port,
            'timeout'  => 5,
        ]);
    }

    // === PPPoE Management ===

    public function createPppoeUser(Customer $customer): void
    {
        $nas = $customer->nasDevice;
        $this->connect($nas);

        $this->client->write('/ppp/secret/add', [
            '=name'     => $customer->username,
            '=password' => $customer->pppoe_password,
            '=service'  => 'pppoe',
            '=profile'  => $customer->package->mikrotik_profile,
            '=comment'  => "Customer ID: {$customer->id}",
        ]);
    }

    public function deletePppoeUser(string $username): void
    {
        $response = $this->client->write('/ppp/secret/print', [
            '?name' => $username,
        ]);

        foreach ($response as $item) {
            $this->client->write('/ppp/secret/remove', [
                '=.id' => $item['.id'],
            ]);
        }
    }

    public function disconnectPppoeUser(string $username): void
    {
        $response = $this->client->write('/ppp/active/print', [
            '?name' => $username,
        ]);

        foreach ($response as $item) {
            $this->client->write('/ppp/active/remove', [
                '=.id' => $item['.id'],
            ]);
        }
    }

    // === Hotspot Management ===

    public function createHotspotUser(Customer $customer): void
    {
        $nas = $customer->nasDevice;
        $this->connect($nas);

        $this->client->write('/ip/hotspot/user/add', [
            '=name'     => $customer->username,
            '=password' => $customer->hotspot_password,
            '=profile'  => $customer->package->mikrotik_profile,
            '=comment'  => "Customer ID: {$customer->id}",
        ]);
    }

    // === Status Monitoring ===

    public function getActivePppoeSessions(NasDevice $nas): array
    {
        $this->connect($nas);
        return $this->client->write('/ppp/active/print') ?? [];
    }

    public function getActiveHotspotSessions(NasDevice $nas): array
    {
        $this->connect($nas);
        return $this->client->write('/ip/hotspot/active/print') ?? [];
    }

    public function pingDevice(NasDevice $nas): bool
    {
        $socket = @fsockopen($nas->ip_address, $nas->api_port, $errno, $errstr, 3);
        if ($socket) {
            fclose($socket);
            $nas->update(['last_seen_at' => now()]);
            return true;
        }
        return false;
    }

    // === Suspend / Unsuspend ===

    public function suspendUser(Customer $customer): void
    {
        $type = $customer->service_type;

        if ($type === 'pppoe') {
            $this->disconnectPppoeUser($customer->username);
            // Disable the secret
            $this->disablePppoeUser($customer->username);
        } elseif ($type === 'hotspot') {
            $this->disableHotspotUser($customer->username);
        }
    }

    public function activateUser(Customer $customer): void
    {
        $type = $customer->service_type;

        if ($type === 'pppoe') {
            $this->enablePppoeUser($customer->username);
        } elseif ($type === 'hotspot') {
            $this->enableHotspotUser($customer->username);
        }
    }
}
```

## NasController (Bulk Update)

```php
// app/Http/Controllers/NasController.php
class NasController extends Controller
{
    public function index()
    {
        $nasDevices = NasDevice::withCount('customers')
            ->get()
            ->map(function ($nas) {
                $nas->is_online = app(MikrotikService::class)->pingDevice($nas);
                return $nas;
            });

        return view('nas.index', compact('nasDevices'));
    }

    // Bulk update multiple NAS sekaligus
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'ids'    => 'required|array',
            'ids.*'  => 'exists:nas_devices,id',
            'field'  => 'required|in:is_active,type,vpn_type',
            'value'  => 'required',
        ]);

        NasDevice::whereIn('id', $request->ids)
            ->update([$request->field => $request->value]);

        return back()->with('success', count($request->ids) . ' NAS berhasil diupdate');
    }
}
```

## WireGuard VPN Setup (Script)

```bash
#!/bin/bash
# Setup WireGuard untuk koneksi ke NAS

# Install WireGuard
apt install wireguard -y

# Generate key pair
wg genkey | tee /etc/wireguard/privatekey | wg pubkey > /etc/wireguard/publickey

PRIVATE_KEY=$(cat /etc/wireguard/privatekey)
PUBLIC_KEY=$(cat /etc/wireguard/publickey)

echo "Private Key: $PRIVATE_KEY"
echo "Public Key: $PUBLIC_KEY"

# Konfigurasi WireGuard interface
cat > /etc/wireguard/wg0.conf << EOF
[Interface]
Address = 10.0.0.1/24
ListenPort = 51820
PrivateKey = $PRIVATE_KEY

[Peer]
# MikroTik Router
PublicKey = <MIKROTIK_PUBLIC_KEY>
AllowedIPs = 10.0.0.2/32
EOF

# Enable dan start
systemctl enable wg-quick@wg0
systemctl start wg-quick@wg0
```
