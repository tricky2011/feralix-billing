@extends('layouts.app')

@section('title', 'Login - Feralix Billing')

@section('content')
    <main
        x-data="loginPage()"
        x-init="init()"
        class="relative flex min-h-screen items-center justify-center overflow-hidden px-6 py-12"
    >
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_15%_10%,rgba(22,101,52,0.18),transparent_32%),radial-gradient(circle_at_80%_20%,rgba(180,83,9,0.16),transparent_30%),linear-gradient(135deg,#f4efe4,#e8efe4_55%,#f6dfc8)]"></div>
        <div class="absolute left-10 top-10 h-28 w-28 rounded-full border border-emerald-900/20"></div>
        <div class="absolute bottom-12 right-12 h-44 w-44 rounded-full bg-amber-700/10 blur-2xl"></div>

        <section class="relative grid w-full max-w-5xl overflow-hidden rounded-[2rem] border border-white/70 bg-white/60 shadow-2xl shadow-emerald-950/10 backdrop-blur-xl lg:grid-cols-[1.1fr_0.9fr]">
            <div class="hidden min-h-[620px] flex-col justify-between bg-emerald-950 p-10 text-stone-100 lg:flex">
                <div>
                    <div class="inline-flex items-center gap-3 rounded-full bg-white/10 px-4 py-2 text-sm text-emerald-50">
                        <span class="h-2 w-2 rounded-full bg-emerald-300"></span>
                        ISP operations cockpit
                    </div>
                    <h1 class="mt-10 max-w-md font-display text-6xl leading-none text-stone-50">
                        Network billing, tanpa drama operasional.
                    </h1>
                </div>

                <div class="grid gap-4">
                    <div class="rounded-3xl border border-white/10 bg-white/10 p-5">
                        <p class="text-sm uppercase tracking-[0.3em] text-emerald-200">Phase 1</p>
                        <p class="mt-3 text-lg text-stone-100">Dashboard, customer, service, billing, isolir, network, tiket, work order, hotspot basic.</p>
                    </div>
                    <div class="flex items-center gap-3 text-sm text-stone-300">
                        <span class="h-px flex-1 bg-white/20"></span>
                        API v1 envelope ready
                    </div>
                </div>
            </div>

            <div class="p-8 sm:p-12">
                <div class="mb-10">
                    <p class="text-sm font-semibold uppercase tracking-[0.28em] text-emerald-700">Feralix Billing</p>
                    <h2 class="mt-4 font-display text-5xl leading-none text-slate-950">Masuk admin</h2>
                    <p class="mt-4 max-w-md text-sm leading-6 text-slate-600">
                        Gunakan akun panel. Token disimpan lokal di browser dan tidak pernah ditampilkan di UI.
                    </p>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <x-form-input name="username" label="Username" type="text" placeholder="arya" x-model="form.username" required />
                    <x-form-input name="password" label="Password" type="password" placeholder="••••••••" x-model="form.password" required />
                    <x-form-input name="device_name" label="Nama perangkat" type="text" placeholder="Admin Panel Browser" x-model="form.device_name" />

                    <div x-show="message" x-cloak class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="message"></div>

                    <button
                        type="submit"
                        class="group flex w-full items-center justify-center gap-3 rounded-2xl bg-emerald-950 px-5 py-4 text-sm font-bold text-white shadow-lg shadow-emerald-950/20 transition hover:-translate-y-0.5 hover:bg-emerald-900 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="loading"
                    >
                        <span x-show="!loading">Masuk ke panel</span>
                        <span x-show="loading" x-cloak>Memverifikasi...</span>
                        <span class="h-2 w-2 rounded-full bg-amber-300 transition group-hover:scale-150"></span>
                    </button>
                </form>
            </div>
        </section>
    </main>
@endsection
