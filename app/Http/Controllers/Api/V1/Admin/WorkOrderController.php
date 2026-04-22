<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\IndexWorkOrderRequest;
use App\Http\Requests\WorkOrder\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderRequest;
use App\Http\Resources\WorkOrderResource;
use App\Models\WorkOrder;
use App\Services\FieldWork\WorkOrderService;
use Illuminate\Http\JsonResponse;

class WorkOrderController extends Controller
{
    public function __construct(private readonly WorkOrderService $workOrderService) {}

    public function index(IndexWorkOrderRequest $request)
    {
        $workOrders = $this->workOrderService->paginate($request->validated());

        return WorkOrderResource::collection($workOrders)->additional([
            'message' => 'Work orders retrieved successfully.',
            'filters' => $request->validated(),
        ]);
    }

    public function store(StoreWorkOrderRequest $request): JsonResponse
    {
        $workOrder = $this->workOrderService->create($request->validated());

        return response()->json([
            'message' => 'Work order created successfully.',
            'data' => new WorkOrderResource($workOrder),
        ], 201);
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        return response()->json([
            'message' => 'Work order retrieved successfully.',
            'data' => new WorkOrderResource($this->workOrderService->find($workOrder)),
        ]);
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $workOrder = $this->workOrderService->update($workOrder, $request->validated());

        return response()->json([
            'message' => 'Work order updated successfully.',
            'data' => new WorkOrderResource($workOrder),
        ]);
    }

    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        $this->workOrderService->delete($workOrder);

        return response()->json([
            'message' => 'Work order deleted successfully.',
        ]);
    }
}
