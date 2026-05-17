# Fitur 10: VPN Management (WireGuard & L2TP)

## Database Migration

```php
Schema::create('vpn_configs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('nas_id')->constrained('nas_devices')->cascadeOnDelete();
    $table->enum('type', ['wireguard', 'l2tp'])->default('wireguard');
    $table->string('endpoint');         // IP:port VPN server
    $table->string('interface_ip', 45)->nullable(); // IP lokal interface
    $table->string('public_key')->nullable();
    $table->string('private_key')->nullable();
    $table->string('preshared_key')->nullable();
    $table->string('l2tp_username')->nullable();
    $table->string('l2tp_password')->nullable();
    $table->string('l2tp_secret')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamp('connected_at')->nullable();
    $table->timestamps();
});
```

## WireGuardService

```php
// app/Services/VPN/WireGuardService.php
class WireGuardService
{
    public function generateKeyPair(): array
    {
        $privateKey = trim(shell_exec('wg genkey'));
        $publicKey  = trim(shell_exec("echo '{$privateKey}' | wg pubkey"));

        return [
            'private_key' => $privateKey,
            'public_key'  => $publicKey,
        ];
    }

    public function buildConfig(VpnConfig $vpn): string
    {
        return "[Interface]
Address = {$vpn->interface_ip}
PrivateKey = {$vpn->private_key}

[Peer]
PublicKey = {$vpn->public_key}
Endpoint = {$vpn->endpoint}
AllowedIPs = 0.0.0.0/0
PersistentKeepalive = 25
";
    }

    public function writeConfig(VpnConfig $vpn): void
    {
        $interfaceName = "wg-nas{$vpn->nas_id}";
        $configPath    = "/etc/wireguard/{$interfaceName}.conf";

        file_put_contents($configPath, $this->buildConfig($vpn));
        chmod($configPath, 0600);
    }

    public function connect(VpnConfig $vpn): bool
    {
        $interfaceName = "wg-nas{$vpn->nas_id}";
        $this->writeConfig($vpn);

        $output = shell_exec("wg-quick up {$interfaceName} 2>&1");
        $success = !str_contains($output ?? '', 'Error');

        if ($success) {
            $vpn->update(['connected_at' => now()]);
        }

        return $success;
    }

    public function disconnect(VpnConfig $vpn): bool
    {
        $interfaceName = "wg-nas{$vpn->nas_id}";
        $output = shell_exec("wg-quick down {$interfaceName} 2>&1");

        $vpn->update(['connected_at' => null]);
        return !str_contains($output ?? '', 'Error');
    }

    public function status(VpnConfig $vpn): array
    {
        $interfaceName = "wg-nas{$vpn->nas_id}";
        $output = shell_exec("wg show {$interfaceName} 2>&1");

        return [
            'is_connected' => !str_contains($output ?? '', 'No such device'),
            'raw'          => $output,
        ];
    }
}
```

## L2TPService

```php
// app/Services/VPN/L2TPService.php
class L2TPService
{
    public function buildConfig(VpnConfig $vpn): string
    {
        $nasId = $vpn->nas_id;

        // xl2tpd.conf
        return "[lac nas{$nasId}]
lns = {$vpn->endpoint}
ppp debug = no
pppoptfile = /etc/ppp/peers/l2tp-nas{$nasId}
length bit = yes
";
    }

    public function buildPppConfig(VpnConfig $vpn): string
    {
        $nasId = $vpn->nas_id;
        return "remotename L2TP
name {$vpn->l2tp_username}
password {$vpn->l2tp_password}
require-mschap-v2
noccp
noauth
idle 1800
mtu 1410
mru 1410
defaultroute
usepeerdns
connect-delay 5000
";
    }

    public function writeConfig(VpnConfig $vpn): void
    {
        $nasId = $vpn->nas_id;
        file_put_contents("/etc/xl2tpd/xl2tpd.conf", $this->buildConfig($vpn), FILE_APPEND);
        file_put_contents("/etc/ppp/peers/l2tp-nas{$nasId}", $this->buildPppConfig($vpn));
    }

    public function connect(VpnConfig $vpn): bool
    {
        $this->writeConfig($vpn);
        $nasId = $vpn->nas_id;

        shell_exec("systemctl restart xl2tpd");
        sleep(1);
        shell_exec("echo 'c nas{$nasId}' > /var/run/xl2tpd/l2tp-control");

        $vpn->update(['connected_at' => now()]);
        return true;
    }
}
```

## VpnController

```php
// app/Http/Controllers/Admin/VpnController.php
class VpnController extends Controller
{
    public function index()
    {
        $nasDevices = NasDevice::with('vpnConfig')->get();
        return view('admin.vpn.index', compact('nasDevices'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nas_id'   => 'required|exists:nas_devices,id',
            'type'     => 'required|in:wireguard,l2tp',
            'endpoint' => 'required|string',
        ]);

        $vpn = VpnConfig::create($request->validated());

        if ($request->type === 'wireguard') {
            $keys = app(WireGuardService::class)->generateKeyPair();
            $vpn->update($keys);
        }

        return redirect()->route('admin.vpn.index')
            ->with('success', 'VPN config berhasil dibuat. Masukkan public key dari MikroTik lalu hubungkan.');
    }

    public function connect(VpnConfig $vpn)
    {
        $success = match($vpn->type) {
            'wireguard' => app(WireGuardService::class)->connect($vpn),
            'l2tp'      => app(L2TPService::class)->connect($vpn),
        };

        return response()->json(['success' => $success]);
    }

    public function disconnect(VpnConfig $vpn)
    {
        if ($vpn->type === 'wireguard') {
            app(WireGuardService::class)->disconnect($vpn);
        }

        return response()->json(['success' => true]);
    }

    public function status(VpnConfig $vpn)
    {
        $status = match($vpn->type) {
            'wireguard' => app(WireGuardService::class)->status($vpn),
            default     => ['is_connected' => (bool) $vpn->connected_at],
        };

        return response()->json($status);
    }
}
```

## Setup Script WireGuard di Server

```bash
#!/bin/bash
# setup-wireguard.sh — jalankan sekali di server RadBill baru

set -e

apt install wireguard -y

# Buat key pair server
wg genkey | tee /etc/wireguard/server_private.key | wg pubkey > /etc/wireguard/server_public.key
chmod 600 /etc/wireguard/server_private.key

SERVER_PRIVATE=$(cat /etc/wireguard/server_private.key)
SERVER_PUBLIC=$(cat /etc/wireguard/server_public.key)

echo "==========================="
echo "Server Public Key:"
echo "$SERVER_PUBLIC"
echo "==========================="
echo "Masukkan public key ini ke konfigurasi MikroTik"

# Interface utama server
cat > /etc/wireguard/wg0.conf << EOF
[Interface]
Address = 10.100.0.1/24
ListenPort = 51820
PrivateKey = $SERVER_PRIVATE

# Peer akan ditambahkan per NAS
# [Peer]
# PublicKey = <NAS_PUBLIC_KEY>
# AllowedIPs = 10.100.0.x/32
EOF

systemctl enable wg-quick@wg0
systemctl start wg-quick@wg0

echo "WireGuard server aktif di port 51820"
echo "Buka firewall: ufw allow 51820/udp"
ufw allow 51820/udp
```

## Konfigurasi MikroTik (RouterOS) untuk WireGuard

```
# Jalankan di terminal MikroTik

# Buat interface WireGuard
/interface wireguard add name=wg-radbill listen-port=13231 mtu=1420

# Print public key MikroTik (berikan ke RadBill)
/interface wireguard print

# Tambah peer (server RadBill)
/interface wireguard peers add interface=wg-radbill \
    public-key="<SERVER_PUBLIC_KEY>" \
    endpoint-address=<SERVER_IP> \
    endpoint-port=51820 \
    allowed-address=10.100.0.0/24 \
    persistent-keepalive=25s

# Tambah IP address
/ip address add address=10.100.0.2/24 interface=wg-radbill

# Test koneksi
/ping 10.100.0.1
```
