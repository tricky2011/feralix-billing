<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\InvoicePaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\AutoSuspendInvoiceRequest;
use App\Http\Requests\Invoice\BulkInvoiceActionRequest;
use App\Http\Requests\Invoice\GenerateMonthlyInvoiceRequest;
use App\Http\Requests\Invoice\IndexInvoiceRequest;
use App\Http\Requests\Invoice\MarkInvoiceOverdueRequest;
use App\Http\Requests\Invoice\MarkInvoicePaidRequest;
use App\Http\Requests\Invoice\SendInvoiceWhatsappRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceDetailResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Services\Billing\InvoiceAutoSuspendService;
use App\Services\Billing\InvoiceBulkActionService;
use App\Services\Billing\InvoiceOverdueService;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\InvoiceWhatsappService;
use App\Services\Billing\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceOverdueService $invoiceOverdueService,
        private readonly PaymentService $paymentService,
        private readonly InvoiceBulkActionService $invoiceBulkActionService,
        private readonly InvoiceWhatsappService $invoiceWhatsappService,
        private readonly InvoiceAutoSuspendService $invoiceAutoSuspendService,
    ) {}

    public function index(IndexInvoiceRequest $request)
    {
        $invoices = $this->invoiceService->paginate($request->validated());

        return InvoiceResource::collection($invoices)->additional([
            'message' => 'Invoices retrieved successfully.',
            'filters' => $request->validated(),
        ]);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create($request->validated());

        return response()->json([
            'message' => 'Invoice created successfully.',
            'data' => new InvoiceDetailResource($invoice),
        ], 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'message' => 'Invoice retrieved successfully.',
            'data' => new InvoiceDetailResource($this->invoiceService->find($invoice)),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoiceService->update($invoice, $request->validated());

        return response()->json([
            'message' => 'Invoice updated successfully.',
            'data' => new InvoiceDetailResource($invoice),
        ]);
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->invoiceService->delete($invoice);

        return response()->json([
            'message' => 'Invoice archived successfully.',
        ]);
    }

    public function manualGenerate(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create($request->validated());

        return response()->json([
            'message' => 'Manual invoice generated successfully.',
            'data' => new InvoiceDetailResource($invoice),
        ], 201);
    }

    public function generateMonthly(GenerateMonthlyInvoiceRequest $request): JsonResponse
    {
        $result = $this->invoiceService->generateMonthlyInvoices($request->validated());

        return response()->json([
            'message' => 'Monthly invoices generated successfully.',
            'data' => InvoiceResource::collection(collect($result['generated']))->resolve(),
            'meta' => [
                'billing_period' => $result['billing_period'],
                'invoice_date' => $result['invoice_date'],
                'due_date' => $result['due_date'],
                'generated_count' => $result['generated_count'],
                'skipped_count' => $result['skipped_count'],
                'skipped' => $result['skipped'],
            ],
        ]);
    }

    public function overdue(IndexInvoiceRequest $request)
    {
        $filters = [
            ...$request->validated(),
            'is_overdue' => true,
        ];

        $invoices = $this->invoiceService->paginate($filters);

        return InvoiceResource::collection($invoices)->additional([
            'message' => 'Overdue invoices retrieved successfully.',
            'filters' => $filters,
        ]);
    }

    public function paid(IndexInvoiceRequest $request)
    {
        return $this->statusListing($request, InvoicePaymentStatus::Paid->value, 'Paid invoices retrieved successfully.');
    }

    public function unpaid(IndexInvoiceRequest $request)
    {
        $filters = [
            ...$request->validated(),
            'payment_statuses' => [
                InvoicePaymentStatus::Unpaid->value,
                InvoicePaymentStatus::Issued->value,
                InvoicePaymentStatus::Overdue->value,
                InvoicePaymentStatus::PartiallyPaid->value,
            ],
        ];

        $invoices = $this->invoiceService->paginate($filters);

        return InvoiceResource::collection($invoices)->additional([
            'message' => 'Unpaid invoices retrieved successfully.',
            'filters' => $filters,
        ]);
    }

    public function markOverdue(MarkInvoiceOverdueRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoiceOverdueService->mark($invoice, $request->validated());

        return response()->json([
            'message' => 'Invoice marked as overdue successfully.',
            'data' => new InvoiceDetailResource($this->invoiceService->find($invoice)),
        ]);
    }

    public function markPaid(MarkInvoicePaidRequest $request, Invoice $invoice): JsonResponse
    {
        $payment = $this->paymentService->settleInvoice($invoice, $request->validated());

        return response()->json([
            'message' => 'Invoice marked as paid successfully.',
            'data' => new InvoiceDetailResource($this->invoiceService->find($invoice)),
            'payment' => new PaymentResource($payment),
        ]);
    }

    public function bulkAction(BulkInvoiceActionRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Bulk invoice action processed successfully.',
            'data' => $this->invoiceBulkActionService->handle($request->validated()),
        ]);
    }

    public function sendWhatsapp(SendInvoiceWhatsappRequest $request, Invoice $invoice): JsonResponse
    {
        $dispatch = $this->invoiceWhatsappService->send($invoice, $request->validated());

        return response()->json([
            'message' => 'Invoice WhatsApp dispatch queued successfully.',
            'data' => [
                'invoice' => new InvoiceDetailResource($this->invoiceService->find($invoice)),
                'dispatch' => $dispatch,
            ],
        ]);
    }

    public function autoSuspend(AutoSuspendInvoiceRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $filters = array_filter([
            'customer_id' => $payload['customer_id'] ?? null,
            'service_id' => $payload['service_id'] ?? null,
            'router_id' => $payload['router_id'] ?? null,
        ], static fn ($value): bool => $value !== null);

        $summary = $this->invoiceAutoSuspendService->trigger(
            filters: $filters,
            referenceDate: isset($payload['reference_date']) ? Carbon::parse($payload['reference_date'])->startOfDay() : null,
            chunkSize: (int) ($payload['chunk'] ?? config('automation.billing.service_isolation.chunk', 100)),
        );

        return response()->json([
            'message' => 'Invoice auto suspend trigger processed successfully.',
            'data' => $summary,
        ]);
    }

    private function statusListing(IndexInvoiceRequest $request, string $status, string $message)
    {
        $filters = [
            ...$request->validated(),
            'payment_status' => $status,
        ];

        $invoices = $this->invoiceService->paginate($filters);

        return InvoiceResource::collection($invoices)->additional([
            'message' => $message,
            'filters' => $filters,
        ]);
    }
}
