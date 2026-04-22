<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ont\IndexOntRequest;
use App\Http\Requests\Ont\StoreOntRequest;
use App\Http\Requests\Ont\UpdateOntRequest;
use App\Http\Resources\OntResource;
use App\Models\Ont;
use Illuminate\Http\JsonResponse;

class OntController extends Controller
{
    public function index(IndexOntRequest $request)
    {
        $filters = $request->validated();
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $onts = Ont::query()
            ->with('olt:id,olt_code,olt_name')
            ->search($filters['search'] ?? null)
            ->when(
                $filters['olt_id'] ?? null,
                fn ($query, $oltId) => $query->where('olt_id', $oltId)
            )
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->orderByDesc('last_inform_at')
            ->orderByDesc('last_seen_at')
            ->orderBy('ont_sn')
            ->paginate($perPage)
            ->withQueryString();

        return OntResource::collection($onts)->additional([
            'message' => 'ONTs retrieved successfully.',
            'filters' => $filters,
        ]);
    }

    public function store(StoreOntRequest $request): JsonResponse
    {
        $ont = Ont::query()->create($request->validated());

        return response()->json([
            'message' => 'ONT created successfully.',
            'data' => new OntResource($ont->load('olt:id,olt_code,olt_name')),
        ], 201);
    }

    public function show(Ont $ont): JsonResponse
    {
        return response()->json([
            'message' => 'ONT retrieved successfully.',
            'data' => new OntResource($ont->load('olt:id,olt_code,olt_name')),
        ]);
    }

    public function update(UpdateOntRequest $request, Ont $ont): JsonResponse
    {
        $ont->update($request->validated());

        return response()->json([
            'message' => 'ONT updated successfully.',
            'data' => new OntResource($ont->refresh()->load('olt:id,olt_code,olt_name')),
        ]);
    }

    public function destroy(Ont $ont): JsonResponse
    {
        $ont->delete();

        return response()->json([
            'message' => 'ONT deleted successfully.',
        ]);
    }
}
