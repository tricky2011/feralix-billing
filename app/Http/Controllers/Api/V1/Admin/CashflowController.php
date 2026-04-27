<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashflow\IndexCashflowRequest;
use App\Http\Requests\Cashflow\StoreCashflowRequest;
use App\Http\Requests\Cashflow\UpdateCashflowRequest;
use App\Http\Resources\CashflowCategoryResource;
use App\Http\Resources\CashflowResource;
use App\Models\Cashflow;
use App\Services\Audit\ActivityLogger;
use App\Services\Finance\CashflowService;
use Illuminate\Http\JsonResponse;

class CashflowController extends Controller
{
    public function __construct(
        private readonly CashflowService $cashflowService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(IndexCashflowRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $cashflows = $this->cashflowService->paginate($filters);

        return $this->paginatedResponse(
            $cashflows,
            CashflowResource::class,
            'Cashflows retrieved successfully.',
            [
                'filters' => $filters,
                'categories' => CashflowCategoryResource::collection($this->cashflowService->categories()),
            ],
        );
    }

    public function store(StoreCashflowRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['created_by'] = $request->user()?->id;

        $cashflow = $payload['type'] === 'income'
            ? $this->cashflowService->createIncome($payload)
            : $this->cashflowService->createExpense($payload);

        $this->activityLogger->record(
            $request->user(),
            'cashflow.created',
            'cashflow',
            sprintf('Created %s cashflow #%d.', $cashflow->type, $cashflow->id),
            $request,
        );

        return $this->createdResponse('Cashflow created successfully.', new CashflowResource($cashflow));
    }

    public function show(Cashflow $cashflow): JsonResponse
    {
        return $this->successResponse(
            'Cashflow retrieved successfully.',
            new CashflowResource($this->cashflowService->find($cashflow)),
        );
    }

    public function update(UpdateCashflowRequest $request, Cashflow $cashflow): JsonResponse
    {
        $cashflow = $this->cashflowService->updateManual($cashflow, $request->validated());

        $this->activityLogger->record(
            $request->user(),
            'cashflow.updated',
            'cashflow',
            sprintf('Updated manual cashflow #%d.', $cashflow->id),
            $request,
        );

        return $this->successResponse('Cashflow updated successfully.', new CashflowResource($cashflow));
    }

    public function destroy(Cashflow $cashflow): JsonResponse
    {
        $cashflowId = $cashflow->id;
        $this->cashflowService->deleteManual($cashflow);

        $this->activityLogger->record(
            request()->user(),
            'cashflow.deleted',
            'cashflow',
            sprintf('Deleted manual cashflow #%d.', $cashflowId),
            request(),
        );

        return $this->successResponse('Cashflow deleted successfully.');
    }

    public function summary(IndexCashflowRequest $request): JsonResponse
    {
        return $this->successResponse(
            'Cashflow summary retrieved successfully.',
            $this->cashflowService->summary($request->validated()),
        );
    }
}
