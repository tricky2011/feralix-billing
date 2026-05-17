<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PonPort\IndexPonPortRequest;
use App\Http\Requests\PonPort\StorePonPortRequest;
use App\Http\Requests\PonPort\UpdatePonPortRequest;
use App\Http\Resources\PonPortResource;
use App\Models\Olt;
use App\Models\PonPort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PonPortController extends Controller
{
    public function index(IndexPonPortRequest $request, Olt $olt): JsonResponse
    {
        $perPage = max(1, min((int) ($request->validated('per_page') ?? 50), 500));

        $ponPorts = $olt->ponPorts()
            ->orderBy('port_number')
            ->paginate($perPage);

        return $this->paginatedResponse(
            $ponPorts,
            PonPortResource::class,
            'PON Ports retrieved successfully.',
        );
    }

    public function store(StorePonPortRequest $request, Olt $olt): JsonResponse
    {
        $existingCount = $olt->ponPorts()->count();
        $ponPort = $olt->ponPorts()->create([
            'port_number' => $request->validated('port_number'),
            'name' => $request->validated('name') ?? ('PON-' . $request->validated('port_number')),
            'max_capacity' => $request->validated('max_capacity', 100),
            'current_count' => 0,
            'is_active' => $request->boolean('is_active', true),
            'notes' => $request->validated('notes'),
        ]);

        return $this->createdResponse('PON Port created successfully.', new PonPortResource($ponPort));
    }

    public function show(Olt $olt, PonPort $ponPort): JsonResponse
    {
        if ($ponPort->olt_id !== $olt->id) {
            abort(404, 'PON Port not found for this OLT');
        }

        return $this->successResponse('PON Port retrieved successfully.', new PonPortResource($ponPort));
    }

    public function update(UpdatePonPortRequest $request, Olt $olt, PonPort $ponPort): JsonResponse
    {
        if ($ponPort->olt_id !== $olt->id) {
            abort(404, 'PON Port not found for this OLT');
        }

        $ponPort->update($request->validated());

        return $this->successResponse('PON Port updated successfully.', new PonPortResource($ponPort->fresh()));
    }

    public function destroy(Request $request, Olt $olt, PonPort $ponPort): JsonResponse
    {
        if ($ponPort->olt_id !== $olt->id) {
            abort(404, 'PON Port not found for this OLT');
        }

        if ($ponPort->current_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete PON Port with active customers. Remove customers first.',
                'data'    => null,
                'meta'    => (object) [],
            ], 422);
        }

        $ponPort->delete();

        return $this->successResponse('PON Port deleted successfully.');
    }
}