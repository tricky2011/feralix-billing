@extends('layouts.app')

@section('title', 'Tambah Pelanggan Baru - Feralix Billing')

@section('content')
<div
    x-data="customerProvisioning()"
    x-init="init()"
    class="min-h-screen bg-[#F8FAFC] text-slate-950 transition-colors duration-300 dark:bg-[#07111f] dark:text-slate-100"
>
    <div class="flex min-h-screen">
        <x-sidebar />

        <div class="min-w-0 flex-1 lg:pl-80">
            <x-topbar />

            <main class="px-4 pb-10 pt-24 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-5xl">
                    {{-- Header --}}
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <a
                                href="/admin/customers"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-blue-200 hover:text-blue-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300 dark:hover:text-white"
                            >
                                <span class="flex items-center gap-2">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                    </svg>
                                    Kembali
                                </span>
                            </a>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-emerald-100 px-4 py-2 text-xs font-black uppercase tracking-wide text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                                PROVISIONING AUTO
                            </span>
                        </div>
                    </div>

                    {{-- Form --}}
                    <form @submit.prevent="submitForm" class="space-y-6">
                        {{-- Section 1: Data Pelanggan --}}
                        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                            <h3 class="mb-5 text-lg font-black tracking-tight text-slate-950 dark:text-white">Data Pelanggan</h3>

                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama Pelanggan *</span>
                                    <input
                                        type="text"
                                        x-model="form.full_name"
                                        @input="form.full_name = $event.target.value.toUpperCase()"
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                        placeholder="Nama lengkap"
                                        required
                                    >
                                    <p x-show="errors.full_name" x-text="errors.full_name" class="mt-1 text-xs text-red-600"></p>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Lokasi</span>
                                    <select
                                        x-model="form.location_id"
                                        @change="onLocationChange()"
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                    >
                                        <option value="">Pilih Lokasi...</option>
                                        <template x-for="loc in references.locations" :key="loc.id">
                                            <option :value="loc.id" x-text="(loc.location_code ?? loc.code ?? '') + ' - ' + (loc.location_name ?? loc.name ?? '')"></option>
                                        </template>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">OLT</span>
                                    <select
                                        x-model="form.olt_id"
                                        @change="onOltChange()"
                                        :disabled="!form.location_id"
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                    >
                                        <option value="">Pilih OLT...</option>
                                        <template x-for="olt in filteredOlts()" :key="olt.id">
                                            <option :value="olt.id" x-text="(olt.olt_code ?? olt.code ?? '') + ' - ' + (olt.olt_name ?? olt.name ?? '')"></option>
                                        </template>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Assign Teknisi</span>
                                    <select
                                        x-model="form.assigned_technician_id"
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                    >
                                        <option value="">Pilih Teknisi...</option>
                                        <template x-for="tech in references.technicians" :key="tech.id">
                                            <option :value="tech.id" x-text="(tech.technician_code ?? '') + ' - ' + (tech.full_name ?? tech.name ?? '')"></option>
                                        </template>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Router</span>
                                    <input
                                        type="text"
                                        :value="selectedRouter()"
                                        readonly
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                                        placeholder="Akan auto-fill dari OLT"
                                    >
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">No HP</span>
                                    <input
                                        type="tel"
                                        x-model="form.phone"
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                        placeholder="08xxxxxxxxxx"
                                    >
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Kontak</span>
                                    <input
                                        type="text"
                                        x-model="form.contact"
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                        placeholder="WhatsApp / Telegram"
                                    >
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Email</span>
                                    <input
                                        type="email"
                                        x-model="form.email"
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                        placeholder="email@example.com"
                                    >
                                </label>
                            </div>

                            <div class="mt-4">
                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Alamat</span>
                                    <textarea
                                        x-model="form.address"
                                        rows="2"
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                        placeholder="Alamat lengkap pelanggan"
                                    ></textarea>
                                </label>
                            </div>
                        </section>

                        {{-- Section 2: Data Instalasi --}}
                        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                            <h3 class="mb-5 text-lg font-black tracking-tight text-slate-950 dark:text-white">Data Instalasi</h3>

                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Tanggal Instalasi</span>
                                    <input
                                        type="date"
                                        x-model="form.install_date"
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                    >
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Latitude</span>
                                    <input
                                        type="text"
                                        x-model="form.latitude"
                                        @input="onLatLngChange()"
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                        placeholder="-6.9xxxxxx"
                                    >
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Longitude</span>
                                    <input
                                        type="text"
                                        x-model="form.longitude"
                                        @input="onLatLngChange()"
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                        placeholder="107.6xxxxxx"
                                    >
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Link Google Maps</span>
                                    <input
                                        type="url"
                                        x-model="form.maps_link"
                                        readonly
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                                        placeholder="Auto-generate dari lat/lng"
                                    >
                                </label>
                            </div>
                        </section>

                        {{-- Section 3: Mode Layanan & Koneksi --}}
                        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                            <div class="mb-5 flex items-center justify-between">
                                <h3 class="text-lg font-black tracking-tight text-slate-950 dark:text-white">Mode Layanan & Koneksi</h3>
                                <button
                                    type="button"
                                    @click="generateCredentials()"
                                    :disabled="!canGenerateCredentials()"
                                    class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-600/20 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Generate Username & Password
                                </button>
                            </div>

                            <div class="mb-5 rounded-2xl border border-indigo-200 bg-indigo-50/70 px-4 py-3 dark:border-indigo-500/30 dark:bg-indigo-500/10">
                                <p class="text-sm font-bold text-indigo-800 dark:text-indigo-200">
                                    Mode Layanan: <span class="uppercase">PPPoE</span>
                                    <span class="ml-2 text-xs font-normal text-indigo-600 dark:text-indigo-400">(fixed)</span>
                                </p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Username PPP *</span>
                                    <input
                                        type="text"
                                        x-model="form.pppoe_username"
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-mono uppercase outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                        placeholder="NAMA-KODELOKASI-KODEOLT"
                                        required
                                    >
                                    <p x-show="errors.pppoe_username" x-text="errors.pppoe_username" class="mt-1 text-xs text-red-600"></p>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Password PPP *</span>
                                    <input
                                        type="text"
                                        x-model="form.pppoe_password"
                                        readonly
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-mono outline-none dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                                        placeholder="ddmmyyyy"
                                    >
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">VID PPPoE Server</span>
                                    <select
                                        x-model="form.pppoe_server"
                                        :disabled="!form.router_id || loadingPppoeServers"
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                    >
                                        <option value="">Pilih PPPoE Server...</option>
                                        <template x-for="server in pppoeServers" :key="server.name">
                                            <option :value="server.name" x-text="server.label"></option>
                                        </template>
                                    </select>
                                    <p x-show="loadingPppoeServers" class="mt-1 text-xs text-slate-500">Memuat PPPoE servers...</p>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">VID Pelanggan (Internet)</span>
                                    <div class="mt-2 flex items-center gap-2">
                                        <input
                                            type="text"
                                            :value="assignedVid()"
                                            readonly
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-mono outline-none dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                                            placeholder="V-XXXX (IP range)"
                                        >
                                        <button
                                            type="button"
                                            @click="refreshAssignedVid()"
                                            :disabled="!form.router_id || loadingAssignedVid"
                                            class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-bold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:text-slate-200"
                                            title="Refresh VID"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <p x-show="!assignedVid() && !loadingAssignedVid && form.router_id" class="mt-1 text-xs text-red-600">Tidak ada VID tersedia di router ini</p>
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">IP Address</span>
                                    <input
                                        type="text"
                                        :value="assignedIpAddress()"
                                        readonly
                                        class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-mono outline-none dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                                        placeholder="Akan di-assign otomatis"
                                    >
                                </label>

                                <label class="block">
                                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</span>
                                    <input
                                        type="text"
                                        value="PENDING"
                                        readonly
                                        class="mt-2 w-full rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold uppercase text-amber-700 outline-none dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300"
                                    >
                                </label>
                            </div>
                        </section>

                        {{-- Submit --}}
                        <div class="flex items-center justify-end gap-4">
                            <a
                                href="/admin/customers"
                                class="rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-bold text-slate-700 transition hover:border-blue-200 hover:text-blue-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300 dark:hover:text-white"
                            >
                                Kembali
                            </a>
                            <button
                                type="submit"
                                :disabled="saving"
                                class="rounded-xl bg-blue-600 px-8 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/20 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <span x-show="!saving">Simpan Pelanggan</span>
                                <span x-show="saving">Menyimpan...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <x-modal />
    <x-toast-notification />
@endsection
