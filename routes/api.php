<?php

use App\Http\Controllers\Api\V1\Admin\ActivityLogController;
use App\Http\Controllers\Api\V1\Admin\CashflowController;
use App\Http\Controllers\Api\V1\Admin\CustomerController;
use App\Http\Controllers\Api\V1\Admin\CustomerReferenceController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\FiberMapController;
use App\Http\Controllers\Api\V1\Admin\HotspotProfileController;
use App\Http\Controllers\Api\V1\Admin\HotspotVoucherController;
use App\Http\Controllers\Api\V1\Admin\InvoiceController;
use App\Http\Controllers\Api\V1\Admin\LocationController;
use App\Http\Controllers\Api\V1\Admin\ManualIsolirController;
use App\Http\Controllers\Api\V1\Admin\MonitoringController;
use App\Http\Controllers\Api\V1\Admin\NetworkLocationController;
use App\Http\Controllers\Api\V1\Admin\OdcController;
use App\Http\Controllers\Api\V1\Admin\OdpController;
use App\Http\Controllers\Api\V1\Admin\OltController;
use App\Http\Controllers\Api\V1\Admin\OntController;
use App\Http\Controllers\Api\V1\Admin\PackageController;
use App\Http\Controllers\Api\V1\Admin\PaymentController;
use App\Http\Controllers\Api\V1\Admin\ResellerController;
use App\Http\Controllers\Api\V1\Admin\RouterController;
use App\Http\Controllers\Api\V1\Admin\RouterSyncController;
use App\Http\Controllers\Api\V1\Admin\RouterScopeController;
use App\Http\Controllers\Api\V1\Admin\ServiceController;
use App\Http\Controllers\Api\V1\Admin\ServiceIsolationController;
use App\Http\Controllers\Api\V1\Admin\Settings\DatabaseSettingController;
use App\Http\Controllers\Api\V1\Admin\Settings\TelegramBotController;
use App\Http\Controllers\Api\V1\Admin\Settings\TelegramGroupController;
use App\Http\Controllers\Api\V1\Admin\TicketController;
use App\Http\Controllers\Api\V1\Admin\TechnicianDashboardController as AdminTechnicianDashboardController;
use App\Http\Controllers\Api\V1\Admin\UserController;
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
        Route::middleware('panel.role:superadmin,admin,technician')->group(function (): void {
            Route::get('technician-dashboard', [AdminTechnicianDashboardController::class, 'index']);
            Route::post('technician-dashboard/export-pdf', [AdminTechnicianDashboardController::class, 'exportPdf']);
        });

        Route::middleware(['panel.role:superadmin,admin', 'router.scope.bindings'])->group(function (): void {
            Route::patch('users/{user}/disable', [UserController::class, 'disable'])->middleware('role:superadmin,admin');
            Route::patch('users/{user}/enable', [UserController::class, 'enable'])->middleware('role:superadmin,admin');
            Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware('role:superadmin,admin');
            Route::apiResource('users', UserController::class)->middleware('role:superadmin,admin');
            Route::apiResource('activity-logs', ActivityLogController::class)
                ->only(['index', 'show'])
                ->middleware('role:superadmin,admin');
            Route::post('telegram-bots/{telegramBot}/test', [TelegramBotController::class, 'test'])->middleware('role:superadmin,admin');
            Route::apiResource('telegram-bots', TelegramBotController::class)
                ->parameters(['telegram-bots' => 'telegramBot'])
                ->middleware('role:superadmin,admin');
            Route::post('telegram-groups/{telegramGroup}/test', [TelegramGroupController::class, 'test'])->middleware('role:superadmin,admin');
            Route::apiResource('telegram-groups', TelegramGroupController::class)
                ->parameters(['telegram-groups' => 'telegramGroup'])
                ->middleware('role:superadmin,admin');
            Route::get('database-settings', [DatabaseSettingController::class, 'show'])->middleware('role:superadmin,admin');
            Route::patch('database-settings/{databaseSetting}', [DatabaseSettingController::class, 'update'])->middleware('role:superadmin,admin');
            Route::post('database-settings/test', [DatabaseSettingController::class, 'test'])->middleware('role:superadmin,admin');

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
            Route::post('routers/{router}/test-connection', [RouterController::class, 'testConnection'])->middleware('role:superadmin,admin');
            Route::post('routers/{router}/test-acs', [RouterController::class, 'testAcs'])->middleware('role:superadmin,admin');
            Route::post('routers/{router}/sync-ont', [RouterController::class, 'syncOnt'])->middleware('role:superadmin,admin');
            Route::post('router-sync/pppoe', [RouterSyncController::class, 'syncPppoe'])->middleware('role:superadmin,admin');
            Route::post('router-sync/static', [RouterSyncController::class, 'syncStatic'])->middleware('role:superadmin,admin');
            Route::post('router-sync/address-list', [RouterSyncController::class, 'syncAddressList'])->middleware('role:superadmin,admin');
            Route::post('router-sync/all', [RouterSyncController::class, 'syncAll'])->middleware('role:superadmin,admin');
            Route::apiResource('routers', RouterController::class)->middleware('role:superadmin,admin');
            Route::apiResource('router-scopes', RouterScopeController::class);
            Route::apiResource('network-locations', NetworkLocationController::class)
                ->parameters(['network-locations' => 'networkLocation'])
                ->middleware('role:superadmin,admin');
            Route::apiResource('olts', OltController::class)->middleware('role:superadmin,admin');
            Route::apiResource('odcs', OdcController::class)->middleware('role:superadmin,admin');
            Route::apiResource('odps', OdpController::class)->middleware('role:superadmin,admin');
            Route::get('fiber-map', [FiberMapController::class, 'index'])->middleware('role:superadmin,admin');
            Route::get('onts/online', [OntController::class, 'online']);
            Route::get('onts/offline', [OntController::class, 'offline']);
            Route::apiResource('onts', OntController::class);
            Route::get('monitoring/pppoe', [MonitoringController::class, 'pppoe']);
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
            Route::post('isolir/manual', [ManualIsolirController::class, 'isolir']);
            Route::post('isolir/release', [ManualIsolirController::class, 'release']);
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
            Route::get('cashflows/summary', [CashflowController::class, 'summary'])->middleware('role:superadmin,admin');
            Route::apiResource('cashflows', CashflowController::class)->middleware('role:superadmin,admin');
        });
    });

Route::prefix('v1/technician')->middleware(['auth:sanctum', 'panel.role:technician'])->group(function (): void {
    Route::get('dashboard', [TechnicianDashboardController::class, 'show']);
});

Route::prefix('v1/internal')->group(function (): void {
    Route::post('hotspot-radius/authorize', [HotspotRadiusController::class, 'authorize']);
    Route::post('hotspot-radius/accounting', [HotspotRadiusController::class, 'account']);
});
