@extends('layouts.app')

@section('title', 'Admin Panel - Feralix Billing')

@section('content')
    <div
        x-data="adminPanel({ page: @js($page ?? 'dashboard') })"
        x-init="init()"
        class="min-h-screen bg-[#F8FAFC] text-slate-950 transition-colors duration-300 dark:bg-[#07111f] dark:text-slate-100"
    >
        <div class="flex min-h-screen">
            <x-sidebar />

            <div class="min-w-0 flex-1 lg:pl-80">
                <x-topbar />

                <main class="px-4 pb-10 pt-24 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-7xl">
                        <section x-show="page === 'dashboard'" x-cloak>
                            @include('admin.partials.dashboard')
                        </section>

                        <section x-show="page !== 'dashboard'" x-cloak>
                            @include('admin.partials.module')
                        </section>
                    </div>
                </main>
            </div>
        </div>

        <x-modal />
        <x-toast-notification />
        @include('admin.partials.hotspot-voucher-detail')
    </div>
@endsection
