<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ont\IndexOntRequest;
use App\Http\Requests\Ont\StoreOntRequest;
use App\Http\Requests\Ont\UpdateOntRequest;
use App\Http\Resources\OntResource;
use App\Models\Ont;
use App\Services\Access\RoleRouterScopeService;
use Illuminate\Http\JsonResponse;

class OntController extends Controller
{
    public function __construct(
        private readonly RoleRouterScopeService $routerScope,
    ) {}

    public function index(IndexOntRequest $request)
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $onts = Ont::query()
            ->with('olt:id,olt_code,olt_name')
            ->search($filters['search'] ?? null);

        $this->routerScope->applyOltScope($onts, 'router_id');

        $onts
            ->when(
                $filters['olt_id'] ?? null,
                fn ($query, $oltId) => $query->where('olt_id', $oltId)
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $filters['router_id'] ?? null,
                fn ($query, $routerId) => $query->whereHas(
                    'olt',
                    fn ($q) => $q->where('router_id', (int) $routerId)
                )
            )
            ->orderByDesc('last_inform_at')
            ->orderByDesc('last_seen_at')
            ->orderBy('ont_sn');

        $onts = $onts->paginate($perPage)->withQueryString();

        return $this->paginatedResponse(
            $onts,
            OntResource::class,
            'ONTs retrieved successfully.',
            ['filters' => $filters],
        );
    }

    public function store(StoreOntRequest $request): JsonResponse
    {
        $ont = Ont::query()->create($request->validated());

        return $this->createdResponse(
            'ONT created successfully.',
            new OntResource($ont->load('olt:id,olt_code,olt_name')),
        );
    }

    public function show(Ont $ont): JsonResponse
    {
        return $this->successResponse(
            'ONT retrieved successfully.',
            new OntResource($ont->load('olt:id,olt_code,olt_name')),
        );
    }

    public function update(UpdateOntRequest $request, Ont $ont): JsonResponse
    {
        $ont->update($request->validated());

        return $this->successResponse(
            'ONT updated successfully.',
            new OntResource($ont->refresh()->load('olt:id,olt_code,olt_name')),
        );
    }

    public function destroy(Ont $ont): JsonResponse
    {
        $ont->delete();

        return $this->successResponse('ONT deleted successfully.');
    }

    public function online(IndexOntRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $threshold = now()->subMinutes(10);

        $onts = Ont::query()
            ->with('olt:id,olt_code,olt_name')
            ->search($filters['search'] ?? null);

        $this->routerScope->applyOltScope($onts, 'router_id');

        $onts
            ->when($filters['olt_id'] ?? null, fn ($q, $oltId) => $q->where('olt_id', $oltId))
            ->when($filters['router_id'] ?? null, fn ($q, $routerId) => $q->whereHas('olt', fn ($q) => $q->where('router_id', $routerId)))
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $threshold)
            ->orderByDesc('last_seen_at')
            ->orderBy('ont_sn');

        $onts = $onts->paginate($perPage)->withQueryString();

        return $this->paginatedResponse(
            $onts,
            OntResource::class,
            'Online ONTs retrieved.',
            [
                'filters' => $filters,
                'source' => 'genieacs',
                'threshold_minutes' => 10,
            ],
        );
    }

    public function offline(IndexOntRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $threshold = now()->subMinutes(10);

        $onts = Ont::query()
            ->with('olt:id,olt_code,olt_name')
            ->search($filters['search'] ?? null);

        $this->routerScope->applyOltScope($onts, 'router_id');

        $onts
            ->when($filters['olt_id'] ?? null, fn ($q, $oltId) => $q->where('olt_id', $oltId))
            ->when($filters['router_id'] ?? null, fn ($q, $routerId) => $q->whereHas('olt', fn ($q) => $q->where('router_id', $routerId)))
            ->where(function ($query) use ($threshold): void {
                $query
                    ->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $threshold);
            })
            ->orderByDesc('last_seen_at')
            ->orderBy('ont_sn');

        $onts = $onts->paginate($perPage)->withQueryString();

        return $this->paginatedResponse(
            $onts,
            OntResource::class,
            'Offline ONTs retrieved.',
            [
                'filters' => $filters,
                'source' => 'genieacs',
                'threshold_minutes' => 10,
            ],
        );
    }
}
