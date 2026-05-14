<?php

use App\Http\Controllers\HotspotLoginController;
use App\Http\Controllers\IsolirPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::view('/login', 'auth.login')->name('admin.login');

Route::get('/hotspot', HotspotLoginController::class)->name('hotspot.login');

Route::get('/isolir', IsolirPageController::class)->name('isolir');

Route::redirect('/admin', '/admin/dashboard')->name('admin.home');

Route::get('/admin/customers/create', function () {
    return view('admin.index', ['page' => 'customers-create']);
})->name('admin.customers.create');

Route::get('/admin/master-lokasi', function () {
    return view('admin.index', ['page' => 'master-lokasi']);
})->name('admin.master-lokasi');

Route::get('/admin/master-olt', function () {
    return view('admin.index', ['page' => 'master-olt']);
})->name('admin.master-olt');

Route::get('/admin/{page}', function (string $page) {
    abort_unless(in_array($page, [
        'dashboard',
        'customers',
        'services',
        'services',
        'services',
        'billing',
        'isolations',
        'network',
        'tickets',
        'work-orders',
        'hotspot',
        'upgrade-paket',
        'service-plan',
        'ip-pools',
        'router-sync',
        'pppoe-import',
        'cashflow',
        'fiber-network-map',
        'odp-odc',
        'technician-dashboard',
        'monitoring',
        'ont-online',
        'ont-offline',
        'config-acs',
        'user-management',
        'user-logs',
        'settings-telegram',
        'settings-database',
    ], true), 404);

    return view('admin.index', ['page' => $page]);
})->name('admin.panel');
