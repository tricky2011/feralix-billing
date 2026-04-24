@extends('layouts.app')

@section('title', 'Admin Panel - Feralix Billing')

@section('content')
    <div
        x-data="adminPanel({ page: @js($page ?? 'dashboard') })"
        x-init="init()"
        class="min-h-screen bg-[linear-gradient(135deg,#f4efe4_0%,#eef4eb_48%,#f7e2c8_100%)]"
    >
        <div class="flex min-h-screen">
            <x-sidebar />

            <div class="min-w-0 flex-1 lg:pl-72">
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
    </div>
@endsection
