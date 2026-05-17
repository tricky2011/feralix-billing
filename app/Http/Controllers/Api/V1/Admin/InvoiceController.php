<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\InvoicePaymentStatus;
use App\Enums\ServiceNetworkStatus;
use App\Enums\ServiceOverallStatus;
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
use App\Services\Audit\ActivityLogger;
use App\Services\Billing\InvoiceAutoSuspendService;
use App\Services\Billing\InvoiceBulkActionService;
use App\Services\Billing\InvoiceOverdueService;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\InvoiceWhatsappService;
use App\Services\Billing\PaymentService;
use App\Services\Mikrotik\MikrotikPppoeSecretService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    private const FORCE_THRESHOLD = 50;

    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceOverdueService $invoiceOverdueService,
        private readonly PaymentService $paymentService,
        private readonly InvoiceBulkActionService $invoiceBulkActionService,
        private readonly InvoiceWhatsappService $invoiceWhatsappService,
        private readonly InvoiceAutoSuspendService $invoiceAutoSuspendService,
        private readonly MikrotikPppoeSecretService $pppoeSecretService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(IndexInvoiceRequest $request)
    {
        $invoices = $this->invoiceService->paginate($request->validated());

        return $this->paginatedResponse(
            $invoices,
            InvoiceResource::class,
            'Invoices retrieved successfully.',
            ['filters' => $request->validated()],
        );
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create($request->validated());

        return $this->createdResponse('Invoice created successfully.', new InvoiceDetailResource($invoice));
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return $this->successResponse(
            'Invoice retrieved successfully.',
            new InvoiceDetailResource($this->invoiceService->find($invoice)),
        );
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoiceService->update($invoice, $request->validated());

        return $this->successResponse('Invoice updated successfully.', new InvoiceDetailResource($invoice));
    }

    public function destroy(Invoice $invoice): JsonResponse
    {
        $this->invoiceService->delete($invoice);

        return $this->successResponse('Invoice archived successfully.');
    }

    public function manualGenerate(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create($request->validated());

        return $this->createdResponse('Manual invoice generated successfully.', new InvoiceDetailResource($invoice));
    }

    public function generateMonthly(GenerateMonthlyInvoiceRequest $request): JsonResponse
    {
        $result = $this->invoiceService->generateMonthlyInvoices($request->validated());

        return $this->successResponse(
            'Monthly invoices generated successfully.',
            InvoiceResource::collection(collect($result['generated'])),
            [
                'billing_period' => $result['billing_period'],
                'invoice_date' => $result['invoice_date'],
                'due_date' => $result['due_date'],
                'generated_count' => $result['generated_count'],
                'skipped_count' => $result['skipped_count'],
                'skipped' => $result['skipped'],
            ],
        );
    }

    public function overdue(IndexInvoiceRequest $request)
    {
        $filters = [
            ...$request->validated(),
            'is_overdue' => true,
        ];

        $invoices = $this->invoiceService->paginate($filters);

        return $this->paginatedResponse(
            $invoices,
            InvoiceResource::class,
            'Overdue invoices retrieved successfully.',
            ['filters' => $filters],
        );
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

        return $this->paginatedResponse(
            $invoices,
            InvoiceResource::class,
            'Unpaid invoices retrieved successfully.',
            ['filters' => $filters],
        );
    }

    public function markOverdue(MarkInvoiceOverdueRequest $request, Invoice $invoice): JsonResponse
    {
        $invoice = $this->invoiceOverdueService->mark($invoice, $request->validated());

        return $this->successResponse(
            'Invoice marked as overdue successfully.',
            new InvoiceDetailResource($this->invoiceService->find($invoice)),
        );
    }

    public function markPaid(MarkInvoicePaidRequest $request, Invoice $invoice): JsonResponse
    {
        $payment = $this->paymentService->settleInvoice($invoice, $request->validated());

        // Prepaid flow: enable PPPoE when first invoice is paid
        $invoice->loadMissing(['service.router']);
        $service = $invoice->service;

        if ($service && $service->overall_status?->value === ServiceOverallStatus::Provisioning->value && $service->pppoe_username) {
            $router = $service->router;
            if ($router) {
                try {
                    $this->pppoeSecretService->setSecretEnabled($router, $service->pppoe_username, true);

                    $service->update([
                        'overall_status' => ServiceOverallStatus::Active->value,
                        'network_status' => ServiceNetworkStatus::Active->value,
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to enable PPPoE after payment', [
                        'service_id'     => $service->id,
                        'pppoe_username' => $service->pppoe_username,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }
        }

        return $this->successResponse('Invoice marked as paid successfully.', [
            'invoice' => new InvoiceDetailResource($this->invoiceService->find($invoice)),
            'payment' => new PaymentResource($payment),
        ]);
    }

    public function bulkAction(BulkInvoiceActionRequest $request): JsonResponse
    {
        return $this->successResponse(
            'Bulk invoice action processed successfully.',
            $this->invoiceBulkActionService->handle($request->validated()),
        );
    }

    public function sendWhatsapp(SendInvoiceWhatsappRequest $request, Invoice $invoice): JsonResponse
    {
        $dispatch = $this->invoiceWhatsappService->send($invoice, $request->validated());

        return $this->successResponse('Invoice WhatsApp dispatch queued successfully.', [
            'invoice' => new InvoiceDetailResource($this->invoiceService->find($invoice)),
            'dispatch' => $dispatch,
        ]);
    }

    public function autoSuspend(AutoSuspendInvoiceRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $dryRun = $request->boolean('dry_run', false);

        $filters = array_filter([
            'customer_id' => $payload['customer_id'] ?? null,
            'service_id' => $payload['service_id'] ?? null,
            'router_id' => $payload['router_id'] ?? null,
        ], static fn ($value): bool => $value !== null);

        $referenceDate = isset($payload['reference_date'])
            ? Carbon::parse($payload['reference_date'])->startOfDay()
            : null;

        $affectedCount = $this->invoiceAutoSuspendService->countAffected($filters, $referenceDate);

        if ($affectedCount > self::FORCE_THRESHOLD && !$request->boolean('force', false)) {
            throw ValidationException::withMessages([
                'force' => [
                    sprintf(
                        'This operation will affect %d services (threshold: %d). '
                        . 'Set force=true to confirm.',
                        $affectedCount,
                        self::FORCE_THRESHOLD,
                    ),
                ],
            ]);
        }

        $summary = $this->invoiceAutoSuspendService->trigger(
            filters: $filters,
            referenceDate: $referenceDate,
            chunkSize: (int) ($payload['chunk'] ?? config('automation.billing.service_isolation.chunk', 100)),
            dryRun: $dryRun,
        );

        $action = $dryRun ? 'auto-suspend.dry-run' : 'auto-suspend';
        $scopeDesc = $this->buildScopeDescription($filters, $payload['confirm_all'] ?? false);
        $this->activityLogger->record(
            $request->user(),
            $action,
            'invoice-management',
            sprintf(
                'Invoice auto-suspend triggered. %s. Affected: %d, Isolations created: %d, Dry run: %s.',
                $scopeDesc,
                $affectedCount,
                $summary['created_isolations'],
                $dryRun ? 'Yes' : 'No',
            ),
            $request,
        );

        $message = $dryRun
            ? 'Invoice auto suspend dry-run completed successfully.'
            : 'Invoice auto suspend trigger processed successfully.';

        return $this->successResponse($message, array_merge($summary, [
            'affected_invoices' => $affectedCount,
        ]));
    }

    private function buildScopeDescription(array $filters, bool $confirmAll): string
    {
        if ($confirmAll) {
            return 'Scope: all overdue invoices (confirm_all=true)';
        }

        $parts = [];
        if (isset($filters['customer_id'])) {
            $parts[] = sprintf('customer_id=%d', $filters['customer_id']);
        }
        if (isset($filters['service_id'])) {
            $parts[] = sprintf('service_id=%d', $filters['service_id']);
        }
        if (isset($filters['router_id'])) {
            $parts[] = sprintf('router_id=%d', $filters['router_id']);
        }

        return 'Scope: ' . implode(', ', $parts);
    }

    private function statusListing(IndexInvoiceRequest $request, string $status, string $message): JsonResponse
    {
        $filters = [
            ...$request->validated(),
            'payment_status' => $status,
        ];

        $invoices = $this->invoiceService->paginate($filters);

        return $this->paginatedResponse(
            $invoices,
            InvoiceResource::class,
            $message,
            ['filters' => $filters],
        );
    }

    public function downloadPdf(Invoice $invoice): Response
    {
        $invoice->load(['customer', 'service', 'payments']);

        $company = [
            'name'    => config('app.company_name', config('app.name')),
            'address' => config('app.company_address'),
            'phone'   => config('app.company_phone'),
            'email'   => config('app.company_email'),
        ];

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'invoice-%s-%s.pdf',
            $invoice->invoice_number,
            now()->format('Ymd'),
        );

        return $pdf->download($filename);
    }
}
