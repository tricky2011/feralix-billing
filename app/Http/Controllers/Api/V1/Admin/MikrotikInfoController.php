<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contracts\Mikrotik\MikrotikApiClientFactory;
use App\Http\Controllers\Controller;
use App\Models\Router;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MikrotikInfoController extends Controller
{
    public function __construct(
        private readonly MikrotikApiClientFactory $factory,
    ) {}

    public function pppoeServers(Request $request): JsonResponse
    {
        $request->validate(['router_id' => 'required|exists:routers,id']);
        $router = Router::findOrFail($request->router_id);
        $client = $this->factory->forRouter($router);

        $servers = $client->print('/interface/pppoe-server/server', [
            'service-name',
            'interface',
            'disabled',
        ]);

        // Mikrotik flag: disabled entries have 'disabled' => 'true', active ones omit the key
        $active = array_filter($servers, static fn (array $s): bool => ($s['disabled'] ?? 'false') !== 'true');

        $result = array_map(static function (array $s): array {
            $name = $s['service-name'] ?? $s['name'] ?? '';
            $interface = $s['interface'] ?? '';
            $vid = null;
            if (preg_match('/V-?(\d+)/i', $interface, $m)) {
                $vid = (int) $m[1];
            }

            return [
                'name' => $name,
                'interface' => $interface,
                'vid' => $vid,
                'label' => $name . ($interface ? " ({$interface})" : ''),
            ];
        }, array_values($active));

        return response()->json(['data' => $result]);
    }
}
