<?php

namespace App\Services\Customer;

use App\Enums\CustomerStatus;
use App\Enums\ServiceOverallStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Service;
use App\Services\Billing\InvoiceService;
use App\Services\MasterData\CustomerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerBulkActionService
{
    public function __construct(
        private readonly CustomerService $customerService,
        private readonly InvoiceService $invoiceService,
    ) {}

    public function bulkDelete(array $customerIds): array
    {
        return DB::transaction(function () use ($customerIds): array {
            $customers = Customer::query()
                ->whereIn('id', $customerIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $deleted = [];
            $skipped = [];

            foreach ($customerIds as $customerId) {
                /** @var Customer|null $customer */
                $customer = $customers->get($customerId);

                if ($customer === null) {
                    continue;
                }

                $blockers = $this->customerService->deletionBlockers($customer);

                if ($blockers !== []) {
                    $skipped[] = [
                        'customer_id' => $customer->id,
                        'customer_code' => $customer->customer_code,
                        'reason' => sprintf('Related records still exist: %s.', implode(', ', $blockers)),
                    ];

                    continue;
                }

                $customer->delete();

                $deleted[] = [
                    'customer_id' => $customer->id,
                    'customer_code' => $customer->customer_code,
                ];
            }

            return [
                'requested_count' => count($customerIds),
                'deleted_count' => count($deleted),
                'skipped_count' => count($skipped),
                'deleted' => $deleted,
                'skipped' => $skipped,
            ];
        });
    }

    public function bulkDisable(array $customerIds): array
    {
        return DB::transaction(function () use ($customerIds): array {
            $customers = Customer::query()
                ->whereIn('id', $customerIds)
                ->lockForUpdate()
                ->get();

            $disabled = [];
            $unchanged = [];

            foreach ($customers as $customer) {
                if ($customer->status === CustomerStatus::Inactive) {
                    $unchanged[] = [
                        'customer_id' => $customer->id,
                        'customer_code' => $customer->customer_code,
                        'reason' => 'Customer is already inactive.',
                    ];

                    continue;
                }

                $customer->update([
                    'status' => CustomerStatus::Inactive->value,
                ]);

                $disabled[] = [
                    'customer_id' => $customer->id,
                    'customer_code' => $customer->customer_code,
                ];
            }

            return [
                'requested_count' => count($customerIds),
                'disabled_count' => count($disabled),
                'unchanged_count' => count($unchanged),
                'disabled' => $disabled,
                'unchanged' => $unchanged,
            ];
        });
    }

    public function bulkGenerateInvoice(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $billingPeriod = (string) $payload['billing_period'];
            $invoiceDate = isset($payload['invoice_date'])
                ? Carbon::parse($payload['invoice_date'])->startOfDay()
                : now()->startOfDay();
            $dueDate = isset($payload['due_date'])
                ? Carbon::parse($payload['due_date'])->startOfDay()
                : $invoiceDate->copy()->addDays((int) ($payload['due_in_days'] ?? 10));

            $customers = Customer::query()
                ->whereIn('id', $payload['customer_ids'])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $services = Service::query()
                ->with('package:id,monthly_price')
                ->whereIn('customer_id', $payload['customer_ids'])
                ->whereIn('overall_status', [
                    ServiceOverallStatus::Active->value,
                    ServiceOverallStatus::Down->value,
                    ServiceOverallStatus::Suspended->value,
                    ServiceOverallStatus::Isolated->value,
                ])
                ->lockForUpdate()
                ->orderBy('customer_id')
                ->orderByDesc('id')
                ->get()
                ->groupBy('customer_id');

            $generated = [];
            $skipped = [];

            foreach ($payload['customer_ids'] as $customerId) {
                /** @var Customer|null $customer */
                $customer = $customers->get($customerId);

                if ($customer === null) {
                    continue;
                }

                /** @var Service|null $service */
                $service = $services->get($customerId)?->first();

                if ($service === null) {
                    $skipped[] = [
                        'customer_id' => $customer->id,
                        'customer_code' => $customer->customer_code,
                        'reason' => 'No billable FTTH service found for the selected customer.',
                    ];

                    continue;
                }

                $exists = Invoice::query()
                    ->where('service_id', $service->id)
                    ->where('billing_period', $billingPeriod)
                    ->exists();

                if ($exists) {
                    $skipped[] = [
                        'customer_id' => $customer->id,
                        'customer_code' => $customer->customer_code,
                        'service_id' => $service->id,
                        'service_code' => $service->service_code,
                        'reason' => 'Invoice for this billing period already exists.',
                    ];

                    continue;
                }

                $invoice = $this->invoiceService->create([
                    'customer_id' => $customer->id,
                    'service_id' => $service->id,
                    'billing_period' => $billingPeriod,
                    'invoice_date' => $invoiceDate->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'subtotal' => $service->package->monthly_price,
                    'penalty_amount' => $payload['penalty_amount'] ?? 0,
                ]);

                $generated[] = [
                    'customer_id' => $customer->id,
                    'customer_code' => $customer->customer_code,
                    'service_id' => $service->id,
                    'service_code' => $service->service_code,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'billing_period' => $invoice->billing_period,
                    'total_amount' => $invoice->total_amount,
                ];
            }

            return [
                'requested_count' => count($payload['customer_ids']),
                'generated_count' => count($generated),
                'skipped_count' => count($skipped),
                'generated' => $generated,
                'skipped' => $skipped,
            ];
        });
    }
}
