<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::view('/login', 'auth.login')->name('admin.login');

Route::redirect('/admin', '/admin/dashboard')->name('admin.home');

Route::get('/admin/customers/create', function () {
    return view('admin.customers.create');
})->name('admin.customers.create');

Route::get('/admin/{page}', function (string $page) {
    abort_unless(in_array($page, [
        'dashboard',
        'customers',
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
