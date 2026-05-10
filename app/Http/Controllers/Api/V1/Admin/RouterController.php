<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Router\IndexRouterRequest;
use App\Http\Requests\Router\StoreRouterRequest;
use App\Http\Requests\Router\UpdateRouterRequest;
use App\Http\Resources\RouterResource;
use App\Models\Router;
use App\Services\Access\RoleRouterScopeService;
use App\Services\Audit\ActivityLogger;
use App\Services\RouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RouterController extends Controller
{
    public function __construct(
        private readonly RoleRouterScopeService $roleRouterScopeService,
        private readonly RouterService $routerService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(IndexRouterRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $query = Router::query()
            ->withCount('scopes')
            ->search($filters['search'] ?? null)
            ->when(
                $filters['status'] ?? null,
                fn ($builder, string $status) => $builder->where('status', $status)
            );

        $this->roleRouterScopeService->applyRouterScope($query, 'id');

        $routers = $query
            ->orderByRaw('COALESCE(name, router_name) asc')
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginatedResponse(
            $routers,
            RouterResource::class,
            'Routers retrieved successfully.',
            ['filters' => $filters],
        );
    }

    public function store(StoreRouterRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $router = Router::query()->create($this->buildPersistencePayload($payload));

        $this->activityLogger->record(
            $request->user(),
            'router.created',
            'router-management',
            sprintf('Created router %s (%s).', $router->name ?? '-', $router->host ?? '-'),
            $request,
        );

        return $this->createdResponse('Router created successfully.', new RouterResource($router));
    }

    public function show(Router $router): JsonResponse
    {
        return $this->successResponse(
            'Router retrieved successfully.',
            new RouterResource($router->load('scopes')),
        );
    }

    public function update(UpdateRouterRequest $request, Router $router): JsonResponse
    {
        $payload = $request->validated();
        $updates = $this->buildPersistencePayload($payload, $router, true);

        $router->update($updates);

        $this->activityLogger->record(
            $request->user(),
            'router.updated',
            'router-management',
            sprintf('Updated router %s (%s).', $router->name ?? '-', $router->host ?? '-'),
            $request,
        );

        return $this->successResponse(
            'Router updated successfully.',
            new RouterResource($router->refresh()->load('scopes')),
        );
    }

    public function destroy(Request $request, Router $router): JsonResponse
    {
        $request->validate([]);

        $routerName = $router->name ?? $router->router_name ?? ('#'.$router->id);
        $routerHost = $router->host ?? $router->mgmt_ip ?? '-';

        $router->delete();

        $this->activityLogger->record(
            $request->user(),
            'router.deleted',
            'router-management',
            sprintf('Deleted router %s (%s).', $routerName, $routerHost),
            $request,
        );

        return $this->successResponse('Router deleted successfully.');
    }

    public function testConnection(Request $request, Router $router): JsonResponse
    {
        $request->validate([]);

        $result = $this->routerService->testConnection($router);

        $this->activityLogger->record(
            $request->user(),
            'router.test_connection',
            'router-management',
            sprintf(
                'Test connection router %s (%s): %s.',
                $router->name ?? ('#'.$router->id),
                $router->host ?? '-',
                $result['success'] ? 'success' : 'failed',
            ),
            $request,
        );

        return $this->operationResponse($result);
    }

    public function testAcs(Request $request, Router $router): JsonResponse
    {
        $request->validate([]);

        $result = $this->routerService->testAcs($router);

        $this->activityLogger->record(
            $request->user(),
            'router.test_acs',
            'router-management',
            sprintf(
                'Test ACS router %s (%s): %s.',
                $router->name ?? ('#'.$router->id),
                $router->acs_nbi_url ?? '-',
                $result['success'] ? 'success' : 'failed',
            ),
            $request,
        );

        return $this->operationResponse($result);
    }

    public function syncOnt(Request $request, Router $router): JsonResponse
    {
        $request->validate([]);

        $result = $this->routerService->syncOntPlaceholder($router);

        $this->activityLogger->record(
            $request->user(),
            'router.sync_ont',
            'router-management',
            sprintf('Sync ONT placeholder for router %s (%s).', $router->name ?? ('#'.$router->id), $router->host ?? '-'),
            $request,
        );

        return $this->operationResponse($result);
    }

    public function detectVersion(Request $request, Router $router): JsonResponse
    {
        $request->validate([]);

        $detector = app(\App\Services\Mikrotik\RouterOsVersionDetector::class);
        $factory  = app(\App\Contracts\Mikrotik\MikrotikApiClientFactory::class);

        try {
            $client  = $factory->forRouter($router);
            $version = $detector->detect($router, $client);
            $client->disconnect();

            $router->update(['ros_version' => (string) $version]);

            return $this->successResponse('ROS version detected.', [
                'router_id'   => $router->id,
                'ros_version' => $version,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to detect version.',
                'data' => null,
                'meta' => (object) [],
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildPersistencePayload(array $payload, ?Router $router = null, bool $isUpdate = false): array
    {
        $name = trim((string) ($payload['name'] ?? $router?->name ?? $router?->router_name ?? ''));
        $host = trim((string) ($payload['host'] ?? $router?->host ?? $router?->mgmt_ip ?? ''));

        $status = strtolower((string) ($payload['status'] ?? $router?->status ?? 'active'));
        $status = $status === 'inactive' ? 'inactive' : 'active';

        $routerCode = $payload['router_code']
            ?? $router?->router_code
            ?? $this->generateRouterCode($name);

        $timeout = array_key_exists('timeout', $payload)
            ? $payload['timeout']
            : ($router?->getRawOriginal('timeout') ?? 5);
        $timeout = is_numeric($timeout) && (int) $timeout > 0 ? (int) $timeout : 5;

        $useSsl = array_key_exists('use_ssl', $payload)
            ? (bool) $payload['use_ssl']
            : (bool) ($router?->use_ssl ?? false);

        $data = [
            'name' => $name,
            'host' => $host,
            'api_port' => (int) ($payload['api_port'] ?? $router?->api_port ?? 8728),
            'timeout' => $timeout,
            'use_ssl' => $useSsl,
            'status' => $status,
            'api_username' => array_key_exists('api_username', $payload) ? $payload['api_username'] : $router?->api_username,
            'acs_inform_url' => array_key_exists('acs_inform_url', $payload) ? $payload['acs_inform_url'] : $router?->acs_inform_url,
            'acs_nbi_url' => array_key_exists('acs_nbi_url', $payload) ? $payload['acs_nbi_url'] : $router?->acs_nbi_url,
            'acs_username' => array_key_exists('acs_username', $payload) ? $payload['acs_username'] : $router?->acs_username,
            'description' => array_key_exists('description', $payload) ? $payload['description'] : $router?->description,
            'router_code' => $routerCode,
            'router_name' => $name,
            'router_role' => $payload['router_role'] ?? $router?->router_role ?? 'core',
            'mgmt_ip' => $host,
            'location_name' => array_key_exists('location_name', $payload) ? $payload['location_name'] : $router?->location_name,
            'is_active' => $status === 'active',
            'ros_version' => array_key_exists('ros_version', $payload) ? $payload['ros_version'] : $router?->ros_version,
            'use_rest_api' => array_key_exists('use_rest_api', $payload) ? (bool) $payload['use_rest_api'] : (bool) ($router?->use_rest_api ?? false),
            'rest_port' => array_key_exists('rest_port', $payload) ? (int) ($payload['rest_port'] ?? 443) : ($router?->rest_port ?? 443),
            'rest_tls' => array_key_exists('rest_tls', $payload) ? (bool) $payload['rest_tls'] : (bool) ($router?->rest_tls ?? false),
        ];

        if (array_key_exists('api_password', $payload)) {
            $apiPassword = is_string($payload['api_password']) ? trim($payload['api_password']) : null;

            if ($apiPassword !== '') {
                $data['api_password'] = $apiPassword;
            } elseif (! $isUpdate) {
                $data['api_password'] = null;
            }
        }

        if (array_key_exists('acs_password', $payload)) {
            $acsPassword = is_string($payload['acs_password']) ? trim($payload['acs_password']) : null;

            if ($acsPassword !== '') {
                $data['acs_password'] = $acsPassword;
            } elseif (! $isUpdate) {
                $data['acs_password'] = null;
            }
        }

        if ($isUpdate) {
            if (! array_key_exists('api_password', $payload) || ! isset($data['api_password'])) {
                unset($data['api_password']);
            }

            if (! array_key_exists('acs_password', $payload) || ! isset($data['acs_password'])) {
                unset($data['acs_password']);
            }
        }

        return $data;
    }

    private function generateRouterCode(string $name): string
    {
        $prefix = Str::upper(Str::of($name)->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-')->substr(0, 16)->value());
        $prefix = $prefix !== '' ? $prefix : 'ROUTER';

        $seed = 'RTR-'.$prefix;
        $candidate = Str::limit($seed, 30, '');
        $suffix = 1;

        while (Router::query()->where('router_code', $candidate)->exists()) {
            $suffix++;
            $suffixText = '-'.$suffix;
            $base = Str::limit($seed, max(1, 30 - strlen($suffixText)), '');
            $candidate = $base.$suffixText;
        }

        return $candidate;
    }

    /**
     * @param  array{success:bool,message:string,data:array<mixed>}  $result
     */
    private function operationResponse(array $result): JsonResponse
    {
        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? ''),
            'data' => $result['data'] ?? [],
            'meta' => (object) [],
        ]);
    }
}
