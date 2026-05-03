<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Olt\IndexOltRequest;
use App\Http\Requests\Olt\StoreOltRequest;
use App\Http\Requests\Olt\UpdateOltRequest;
use App\Http\Resources\OltResource;
use App\Models\NetworkLocation;
use App\Models\Olt;
use App\Models\PonPort;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OltController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(IndexOltRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $olts = Olt::query()
            ->with([
                'location:id,location_code,location_name',
                'networkLocation:id,name,code,status',
            ])
            ->withCount(['onts', 'odps'])
            ->search($filters['search'] ?? null)
            ->when(
                $filters['location_id'] ?? null,
                fn ($query, $locationId) => $query->where('network_location_id', $locationId)
            )
            ->when(
                $filters['status'] ?? null,
                function ($query, $status): void {
                    $query->where(function ($statusQuery) use ($status): void {
                        $statusQuery->where('status', $status);

                        if ($status === 'active') {
                            $statusQuery->orWhere(function ($legacyQuery): void {
                                $legacyQuery->whereNull('status')->where('is_active', true);
                            });
                        }

                        if ($status === 'inactive') {
                            $statusQuery->orWhere(function ($legacyQuery): void {
                                $legacyQuery->whereNull('status')->where('is_active', false);
                            });
                        }
                    });
                }
            )
            ->orderByRaw('COALESCE(name, olt_name) asc')
            ->paginate($perPage)
            ->withQueryString();

        return $this->paginatedResponse(
            $olts,
            OltResource::class,
            'OLTs retrieved successfully.',
            ['filters' => $filters],
        );
    }

    public function store(StoreOltRequest $request): JsonResponse
    {
        $olt = Olt::query()->create($this->toPersistencePayload($request->validated()));

        // Auto-generate PON ports
        $totalPon = $request->validated('pon_ports') ?? 4;
        $maxPerPon = $request->validated('max_per_pon') ?? 100;
        for ($i = 1; $i <= $totalPon; $i++) {
            PonPort::create([
                'olt_id' => $olt->id,
                'port_number' => $i,
                'name' => 'PON-' . $i,
                'max_capacity' => $maxPerPon,
                'current_count' => 0,
                'is_active' => true,
            ]);
        }

        $this->activityLogger->record(
            $request->user(),
            'olt.created',
            'network',
            sprintf('Created OLT %s (%s).', $olt->name ?? $olt->olt_name ?? '-', $olt->code ?? $olt->olt_code ?? '-'),
            $request,
        );

        return $this->createdResponse(
            'OLT created successfully.',
            new OltResource($olt->load(['location', 'networkLocation', 'ponPorts'])->loadCount(['onts', 'odps'])),
        );
    }

    public function show(Olt $olt): JsonResponse
    {
        return $this->successResponse(
            'OLT retrieved successfully.',
            new OltResource($olt->load(['onts', 'location', 'networkLocation'])->loadCount(['onts', 'odps'])),
        );
    }

    public function update(UpdateOltRequest $request, Olt $olt): JsonResponse
    {
        $olt->update($this->toPersistencePayload($request->validated(), $olt));

        $this->activityLogger->record(
            $request->user(),
            'olt.updated',
            'network',
            sprintf('Updated OLT %s (%s).', $olt->name ?? $olt->olt_name ?? '-', $olt->code ?? $olt->olt_code ?? '-'),
            $request,
        );

        return $this->successResponse(
            'OLT updated successfully.',
            new OltResource($olt->refresh()->load(['onts', 'location', 'networkLocation'])->loadCount(['onts', 'odps'])),
        );
    }

    public function ponStatus(Olt $olt): JsonResponse
    {
        $ports = $olt->ponPorts()->where('is_active', true)->get()->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'port_number' => $p->port_number,
            'max_capacity' => $p->max_capacity,
            'current_count' => $p->current_count,
            'sisa' => max(0, $p->max_capacity - $p->current_count),
            'is_full' => $p->current_count >= $p->max_capacity,
            'is_almost_full' => ($p->max_capacity - $p->current_count) <= (int) ceil($p->max_capacity * 0.2),
            'status' => $p->current_count >= $p->max_capacity ? 'full'
                : (($p->max_capacity - $p->current_count) <= (int) ceil($p->max_capacity * 0.2) ? 'almost_full' : 'normal'),
        ]);

        $activeCount = $olt->ponPorts()->where('is_active', true)->count();
        $fullPorts = $ports->filter(fn($p) => $p['is_full'])->count();

        return response()->json([
            'olt' => ['id' => $olt->id, 'name' => $olt->name ?? $olt->olt_name],
            'pon_ports' => $ports,
            'active_ports' => $activeCount,
            'full_ports' => $fullPorts,
            'has_full' => $ports->contains('is_full', true),
            'all_full' => $activeCount > 0 && $fullPorts === $activeCount,
            'pon_info' => sprintf('%d/%d PON aktif', $activeCount, $olt->pon_ports ?? 4),
        ]);
    }

    public function destroy(Request $request, Olt $olt): JsonResponse
    {
        $oltName = $olt->name ?? $olt->olt_name ?? ('#'.$olt->id);
        $oltCode = $olt->code ?? $olt->olt_code ?? '-';

        $olt->delete();

        $this->activityLogger->record(
            $request->user(),
            'olt.deleted',
            'network',
            sprintf('Deleted OLT %s (%s).', $oltName, $oltCode),
            $request,
        );

        return $this->successResponse('OLT deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function toPersistencePayload(array $validated, ?Olt $existing = null): array
    {
        $code = isset($validated['code']) ? Str::upper((string) $validated['code']) : ($existing?->code ?? $existing?->olt_code);
        $name = $validated['name'] ?? ($existing?->name ?? $existing?->olt_name);
        $host = $validated['host'] ?? ($existing?->host ?? $existing?->mgmt_ip);
        $brand = $validated['brand'] ?? ($existing?->brand ?? $existing?->vendor_name);
        $status = $validated['status'] ?? ($existing?->status ?? ((bool) $existing?->is_active ? 'active' : 'inactive'));
        $networkLocationId = $validated['location_id'] ?? $existing?->network_location_id;

        if ($code === null || trim($code) === '') {
            $code = $this->generateCodeFallback($name, $existing);
        }

        $networkLocationName = null;

        if ($networkLocationId !== null) {
            $networkLocationName = NetworkLocation::query()->whereKey($networkLocationId)->value('name');
        }

        return [
            'network_location_id' => $networkLocationId,
            'name' => $name,
            'code' => $code,
            'host' => $host,
            'brand' => $brand,
            'model' => $validated['model'] ?? $existing?->model,
            'pon_ports' => $validated['pon_ports'] ?? $existing?->pon_ports,
            'status' => $status,
            'description' => $validated['description'] ?? $existing?->description,

            // Keep legacy columns in sync to avoid regressions in existing modules.
            'olt_code' => $code,
            'olt_name' => $name,
            'mgmt_ip' => $host,
            'vendor_name' => $brand,
            'location_name' => $networkLocationName,
            'is_active' => $status === 'active',
        ];
    }

    private function generateCodeFallback(?string $name, ?Olt $existing = null): string
    {
        if ($existing !== null && $existing->olt_code !== null && $existing->olt_code !== '') {
            return (string) $existing->olt_code;
        }

        $slug = Str::upper(Str::slug((string) ($name ?? 'OLT')));
        $prefix = $slug !== '' ? Str::limit($slug, 40, '') : 'OLT';

        do {
            $candidate = sprintf('%s-%s', $prefix, Str::upper(Str::random(6)));
        } while (
            Olt::query()
                ->where('code', $candidate)
                ->orWhere('olt_code', $candidate)
                ->exists()
        );

        return $candidate;
    }
}
