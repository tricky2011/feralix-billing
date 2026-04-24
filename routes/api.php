<?php

use App\Http\Controllers\Api\V1\Admin\CustomerController;
use App\Http\Controllers\Api\V1\Admin\CustomerReferenceController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\HotspotProfileController;
use App\Http\Controllers\Api\V1\Admin\HotspotVoucherController;
use App\Http\Controllers\Api\V1\Admin\InvoiceController;
use App\Http\Controllers\Api\V1\Admin\LocationController;
use App\Http\Controllers\Api\V1\Admin\OltController;
use App\Http\Controllers\Api\V1\Admin\OntController;
use App\Http\Controllers\Api\V1\Admin\PackageController;
use App\Http\Controllers\Api\V1\Admin\PaymentController;
use App\Http\Controllers\Api\V1\Admin\ResellerController;
use App\Http\Controllers\Api\V1\Admin\RouterController;
use App\Http\Controllers\Api\V1\Admin\RouterScopeController;
use App\Http\Controllers\Api\V1\Admin\ServiceController;
use App\Http\Controllers\Api\V1\Admin\ServiceIsolationController;
use App\Http\Controllers\Api\V1\Admin\TicketController;
use App\Http\Controllers\Api\V1\Admin\VidController;
use App\Http\Controllers\Api\V1\Admin\VoucherBatchController;
use App\Http\Controllers\Api\V1\Admin\WorkOrderController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Internal\HotspotRadiusController;
use App\Http\Controllers\Api\V1\Technician\DashboardController as TechnicianDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('v1/admin')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('dashboard', [AdminDashboardController::class, 'index']);
        Route::patch('dashboard/router-switch', [AdminDashboardController::class, 'switchRouter'])
            ->middleware('panel.role:superadmin,admin');

        Route::middleware(['panel.role:superadmin,admin', 'router.scope.bindings'])->group(function (): void {
            Route::get('customer-references', [CustomerReferenceController::class, 'index']);
            Route::post('customers/onboard', [CustomerController::class, 'onboard']);
            Route::post('customers/bulk-delete', [CustomerController::class, 'bulkDelete']);
            Route::post('customers/bulk-disable', [CustomerController::class, 'bulkDisable']);
            Route::post('customers/bulk-generate-invoice', [CustomerController::class, 'bulkGenerateInvoice']);
            Route::post('customers/provisioning-preview', [CustomerController::class, 'provisioningPreview']);
            Route::apiResource('locations', LocationController::class);
            Route::apiResource('customers', CustomerController::class);
            Route::apiResource('packages', PackageController::class);
            Route::apiResource('hotspot-profiles', HotspotProfileController::class)
                ->parameters(['hotspot-profiles' => 'hotspotProfile'])
                ->except(['destroy']);
            Route::apiResource('resellers', ResellerController::class)->except(['destroy']);
            Route::apiResource('voucher-batches', VoucherBatchController::class)
                ->parameters(['voucher-batches' => 'voucherBatch'])
                ->only(['index', 'store', 'show']);
            Route::apiResource('hotspot-vouchers', HotspotVoucherController::class)
                ->parameters(['hotspot-vouchers' => 'hotspotVoucher'])
                ->only(['index', 'show']);
            Route::post('hotspot-vouchers/{hotspotVoucher}/activate', [HotspotVoucherController::class, 'activate']);
            Route::apiResource('routers', RouterController::class);
            Route::apiResource('router-scopes', RouterScopeController::class);
            Route::apiResource('olts', OltController::class);
            Route::apiResource('onts', OntController::class);
            Route::apiResource('vids', VidController::class);
            Route::apiResource('services', ServiceController::class);
            Route::apiResource('tickets', TicketController::class)->only(['index', 'store', 'show']);
            Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus']);
            Route::post('tickets/{ticket}/replies', [TicketController::class, 'storeReply']);
            Route::apiResource('work-orders', WorkOrderController::class)->parameters([
                'work-orders' => 'workOrder',
            ]);
            Route::get('service-isolations', [ServiceIsolationController::class, 'index']);
            Route::get('service-isolations/suggestions', [ServiceIsolationController::class, 'suggestions']);
            Route::post('service-isolations', [ServiceIsolationController::class, 'store']);
            Route::patch('service-isolations/{serviceIsolation}/applied', [ServiceIsolationController::class, 'markApplied']);
            Route::patch('service-isolations/{serviceIsolation}/release', [ServiceIsolationController::class, 'release']);
            Route::post('invoices/manual-generate', [InvoiceController::class, 'manualGenerate']);
            Route::post('invoices/generate-monthly', [InvoiceController::class, 'generateMonthly']);
            Route::post('invoices/bulk-action', [InvoiceController::class, 'bulkAction']);
            Route::post('invoices/auto-suspend', [InvoiceController::class, 'autoSuspend']);
            Route::get('invoices/overdue', [InvoiceController::class, 'overdue']);
            Route::get('invoices/paid', [InvoiceController::class, 'paid']);
            Route::get('invoices/unpaid', [InvoiceController::class, 'unpaid']);
            Route::patch('invoices/{invoice}/mark-overdue', [InvoiceController::class, 'markOverdue']);
            Route::patch('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid']);
            Route::post('invoices/{invoice}/send-whatsapp', [InvoiceController::class, 'sendWhatsapp']);
            Route::apiResource('invoices', InvoiceController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
            Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'show']);
        });
    });

Route::prefix('v1/technician')->middleware(['auth:sanctum', 'panel.role:technician'])->group(function (): void {
    Route::get('dashboard', [TechnicianDashboardController::class, 'show']);
});

Route::prefix('v1/internal')->group(function (): void {
    Route::post('hotspot-radius/authorize', [HotspotRadiusController::class, 'authorize']);
    Route::post('hotspot-radius/accounting', [HotspotRadiusController::class, 'account']);
});
