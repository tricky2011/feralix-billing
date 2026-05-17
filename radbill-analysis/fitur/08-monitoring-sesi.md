# Fitur 08: Monitoring Sesi Aktif

## MonitoringController

```php
// app/Http/Controllers/Admin/MonitoringController.php
class MonitoringController extends Controller
{
    public function index()
    {
        $nasDevices = NasDevice::where('is_active', true)->get();

        return view('admin.monitoring.index', compact('nasDevices'));
    }

    // AJAX endpoint — polling setiap beberapa detik dari frontend
    public function sessions(Request $request)
    {
        $nasId = $request->nas_id;
        $type  = $request->type ?? 'pppoe'; // pppoe atau hotspot

        $nas = NasDevice::findOrFail($nasId);
        $mikrotik = app(MikrotikService::class);
        $mikrotik->connect($nas);

        $sessions = $type === 'pppoe'
            ? $mikrotik->getActivePppoeSessions($nas)
            : $mikrotik->getActiveHotspotSessions($nas);

        // Enrich dengan data pelanggan dari DB
        $enriched = collect($sessions)->map(function ($session) {
            $username  = $session['name'] ?? $session['user'] ?? null;
            $customer  = $username ? Customer::where('username', $username)->first() : null;

            return [
                'username'   => $username,
                'ip_address' => $session['address'] ?? $session['address'] ?? null,
                'mac'        => $session['caller-id'] ?? null,
                'uptime'     => $session['uptime'] ?? null,
                'rx_bytes'   => $session['bytes-in'] ?? 0,
                'tx_bytes'   => $session['bytes-out'] ?? 0,
                'customer'   => $customer ? [
                    'id'     => $customer->id,
                    'name'   => $customer->name,
                    'status' => $customer->status,
                ] : null,
            ];
        });

        return response()->json([
            'nas'      => $nas->name,
            'count'    => $enriched->count(),
            'sessions' => $enriched->values(),
        ]);
    }

    // Summary semua NAS
    public function summary()
    {
        $summary = NasDevice::where('is_active', true)
            ->get()
            ->map(function ($nas) {
                $online = app(MikrotikService::class)->pingDevice($nas);
                $count  = 0;

                if ($online) {
                    try {
                        $mikrotik = app(MikrotikService::class);
                        $mikrotik->connect($nas);
                        $count = count($mikrotik->getActivePppoeSessions($nas))
                               + count($mikrotik->getActiveHotspotSessions($nas));
                    } catch (\Exception) {}
                }

                return [
                    'id'       => $nas->id,
                    'name'     => $nas->name,
                    'ip'       => $nas->ip_address,
                    'is_online' => $online,
                    'sessions' => $count,
                ];
            });

        return response()->json($summary);
    }

    // Disconnect sesi dari monitoring
    public function disconnect(Request $request)
    {
        $request->validate([
            'nas_id'   => 'required|exists:nas_devices,id',
            'username' => 'required|string',
            'type'     => 'required|in:pppoe,hotspot',
        ]);

        $nas = NasDevice::findOrFail($request->nas_id);
        $mikrotik = app(MikrotikService::class);
        $mikrotik->connect($nas);

        if ($request->type === 'pppoe') {
            $mikrotik->disconnectPppoeUser($request->username);
        } else {
            $mikrotik->disconnectHotspotUser($request->username);
        }

        return response()->json(['success' => true]);
    }
}
```

## View Monitoring Real-time

```html
<!-- resources/views/admin/monitoring/index.blade.php -->
<div class="row">
    <!-- Summary Cards per NAS -->
    <div id="nas-summary" class="row g-3 mb-4">
        <!-- Diisi via JS -->
    </div>

    <!-- Tabel Sesi Aktif -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Sesi Aktif</h5>
                <div class="d-flex gap-2">
                    <select id="nas-select" class="form-select form-select-sm" style="width:200px">
                        @foreach($nasDevices as $nas)
                            <option value="{{ $nas->id }}">{{ $nas->name }}</option>
                        @endforeach
                    </select>
                    <select id="type-select" class="form-select form-select-sm" style="width:120px">
                        <option value="pppoe">PPPoE</option>
                        <option value="hotspot">Hotspot</option>
                    </select>
                    <span id="session-count" class="badge bg-primary align-self-center">0 sesi</span>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>IP Address</th>
                            <th>MAC</th>
                            <th>Uptime</th>
                            <th>Download</th>
                            <th>Upload</th>
                            <th>Pelanggan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="sessions-table">
                        <tr><td colspan="8" class="text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function loadSessions() {
        const nasId = document.getElementById('nas-select').value;
        const type  = document.getElementById('type-select').value;

        fetch(`/admin/monitoring/sessions?nas_id=${nasId}&type=${type}`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('session-count').textContent = data.count + ' sesi';
                const tbody = document.getElementById('sessions-table');

                if (data.sessions.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Tidak ada sesi aktif</td></tr>';
                    return;
                }

                tbody.innerHTML = data.sessions.map(s => `
                    <tr>
                        <td><code>${s.username}</code></td>
                        <td>${s.ip_address ?? '-'}</td>
                        <td>${s.mac ?? '-'}</td>
                        <td>${s.uptime ?? '-'}</td>
                        <td>${formatBytes(s.rx_bytes)}</td>
                        <td>${formatBytes(s.tx_bytes)}</td>
                        <td>${s.customer ? `<a href="/admin/customers/${s.customer.id}">${s.customer.name}</a>` : '<span class="text-muted">-</span>'}</td>
                        <td>
                            <button class="btn btn-xs btn-danger" onclick="disconnectUser('${s.username}', ${nasId}, '${type}')">
                                Disconnect
                            </button>
                        </td>
                    </tr>
                `).join('');
            });
    }

    function loadSummary() {
        fetch('/admin/monitoring/summary')
            .then(r => r.json())
            .then(devices => {
                document.getElementById('nas-summary').innerHTML = devices.map(d => `
                    <div class="col-md-3">
                        <div class="card border-${d.is_online ? 'success' : 'danger'}">
                            <div class="card-body text-center">
                                <h6>${d.name}</h6>
                                <span class="badge bg-${d.is_online ? 'success' : 'danger'}">
                                    ${d.is_online ? 'Online' : 'Offline'}
                                </span>
                                <div class="mt-2 fw-bold fs-4">${d.sessions}</div>
                                <small class="text-muted">Sesi aktif</small>
                            </div>
                        </div>
                    </div>
                `).join('');
            });
    }

    function disconnectUser(username, nasId, type) {
        if (!confirm(`Disconnect ${username}?`)) return;

        fetch('/admin/monitoring/disconnect', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ username, nas_id: nasId, type }),
        }).then(() => loadSessions());
    }

    document.getElementById('nas-select').addEventListener('change', loadSessions);
    document.getElementById('type-select').addEventListener('change', loadSessions);

    // Auto-refresh setiap 30 detik
    loadSummary();
    loadSessions();
    setInterval(loadSessions, 30000);
    setInterval(loadSummary, 60000);
</script>
```
