@extends('layouts.app')

@section('title', 'Master OLT - Feralix Billing')

@section('content')
<div
    x-data="masterOlt()"
    x-init="init()"
    class="min-h-screen bg-[#F8FAFC] text-slate-950 transition-colors duration-300 dark:bg-[#07111f] dark:text-slate-100"
>
    <div class="flex min-h-screen">
        <x-sidebar />

        <div class="min-w-0 flex-1 lg:pl-80">
            <x-topbar />

            <main class="px-4 pb-10 pt-24 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    {{-- Header --}}
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <a
                                href="/admin"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-blue-200 hover:text-blue-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300 dark:hover:text-white"
                            >
                                <span class="flex items-center gap-2">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                    </svg>
                                    Dashboard
                                </span>
                            </a>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-blue-100 px-4 py-2 text-xs font-black uppercase tracking-wide text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">
                                MASTER DATA
                            </span>
                        </div>
                    </div>

                    <h1 class="mb-6 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Master OLT</h1>

                    {{-- Split Layout: Form Kiri 40% | Daftar Kanan 60% --}}
                    <div class="grid gap-6 lg:grid-cols-[2fr_3fr]">
                        {{-- Form Kiri --}}
                        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                            <h2 class="mb-5 text-lg font-black tracking-tight text-slate-950 dark:text-white">
                                <span x-text="editId ? 'Edit OLT' : 'Tambah OLT Baru'"></span>
                            </h2>

                            <form @submit.prevent="submitForm">
                                <div class="space-y-4">
                                    <label class="block">
                                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama OLT *</span>
                                        <input
                                            type="text"
                                            x-model="form.name"
                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                            placeholder="OLT1"
                                            required
                                        >
                                    </label>

                                    <label class="block">
                                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Kode OLT *</span>
                                        <input
                                            type="text"
                                            x-model="form.code"
                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm uppercase outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                            placeholder="OLT01"
                                            required
                                        >
                                        <p class="mt-1 text-xs text-slate-400">Dipakai di PPPoE username.</p>
                                    </label>

                                    <label class="block">
                                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Lokasi</span>
                                        <select
                                            x-model="form.location_id"
                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                        >
                                            <option value="">Pilih Lokasi...</option>
                                            <template x-for="loc in references.locations" :key="loc.id">
                                                <option :value="loc.id" x-text="(loc.location_code ?? loc.code ?? '') + ' - ' + (loc.location_name ?? loc.name ?? '')"></option>
                                            </template>
                                        </select>
                                    </label>

                                    <label class="block">
                                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">IP Address</span>
                                        <input
                                            type="text"
                                            x-model="form.host"
                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                            placeholder="192.168.1.1"
                                        >
                                    </label>

                                    <div class="grid grid-cols-2 gap-4">
                                        <label class="block">
                                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Total PON Port</span>
                                            <input
                                                type="number"
                                                x-model="form.pon_ports"
                                                min="1"
                                                max="32"
                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                                placeholder="4"
                                            >
                                        </label>

                                        <label class="block">
                                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Max Client/PON</span>
                                            <input
                                                type="number"
                                                x-model="form.max_per_pon"
                                                min="1"
                                                max="1000"
                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                                placeholder="100"
                                            >
                                        </label>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <label class="block">
                                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Latitude</span>
                                            <input
                                                type="text"
                                                x-model="form.latitude"
                                                @input="updateMapsLink()"
                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                                placeholder="-6.9123"
                                            >
                                        </label>

                                        <label class="block">
                                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Longitude</span>
                                            <input
                                                type="text"
                                                x-model="form.longitude"
                                                @input="updateMapsLink()"
                                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                                placeholder="107.6098"
                                            >
                                        </label>
                                    </div>

                                    <label class="block">
                                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Link Google Maps</span>
                                        <div class="mt-2 flex items-center gap-2">
                                            <input
                                                type="url"
                                                x-model="form.maps_link"
                                                readonly
                                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                                                placeholder="Auto-generate dari lat/lng"
                                            >
                                            <a
                                                x-show="form.maps_link"
                                                :href="form.maps_link"
                                                target="_blank"
                                                class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-bold text-slate-700 transition hover:border-blue-200 hover:text-blue-700 dark:border-white/10 dark:text-slate-200"
                                                title="Buka di Google Maps"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                </svg>
                                            </a>
                                        </div>
                                    </label>

                                    <label class="block">
                                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Deskripsi</span>
                                        <textarea
                                            x-model="form.description"
                                            rows="2"
                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                            placeholder="Deskripsi opsional..."
                                        ></textarea>
                                    </label>

                                    <label class="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            x-model="form.is_active"
                                            class="h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                        >
                                        <span class="text-sm font-bold text-slate-700 dark:text-slate-300">Aktif</span>
                                    </label>
                                </div>

                                <div class="mt-6 flex items-center gap-3">
                                    <button
                                        type="submit"
                                        :disabled="saving"
                                        class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/20 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <span x-show="!saving">Simpan OLT</span>
                                        <span x-show="saving">Menyimpan...</span>
                                    </button>
                                    <button
                                        type="button"
                                        x-show="editId"
                                        @click="cancelEdit()"
                                        class="rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-bold text-slate-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                                    >
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </section>

                        {{-- Daftar Kanan --}}
                        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                            <div class="mb-5 flex items-center justify-between">
                                <h2 class="text-lg font-black tracking-tight text-slate-950 dark:text-white">
                                    Daftar OLT <span class="ml-2 text-sm font-normal text-slate-400">| Total: <span x-text="pagination.total ?? 0"></span> data</span>
                                </h2>
                            </div>

                            {{-- Search & Filters --}}
                            <div class="mb-4 flex items-center gap-3">
                                <div class="relative flex-1">
                                    <input
                                        type="text"
                                        x-model="filters.search"
                                        @input="debounceSearch()"
                                        placeholder="Cari OLT..."
                                        class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 pl-10 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                    >
                                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                                <button
                                    @click="resetFilters()"
                                    class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                                >
                                    Reset
                                </button>
                            </div>

                            {{-- Bulk Actions --}}
                            <div x-show="selected.length > 0" x-cloak class="mb-4 flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-500/30 dark:bg-blue-500/10">
                                <span class="text-sm font-bold text-blue-700 dark:text-blue-300">
                                    <span x-text="selected.length"></span> dipilih
                                </span>
                                <button
                                    @click="bulkAction('activate')"
                                    class="rounded-lg bg-emerald-500 px-3 py-1 text-xs font-bold text-white"
                                >
                                    Aktifkan
                                </button>
                                <button
                                    @click="bulkAction('deactivate')"
                                    class="rounded-lg bg-amber-500 px-3 py-1 text-xs font-bold text-white"
                                >
                                    Nonaktifkan
                                </button>
                                <button
                                    @click="bulkAction('delete')"
                                    class="rounded-lg bg-red-500 px-3 py-1 text-xs font-bold text-white"
                                >
                                    Hapus
                                </button>
                            </div>

                            {{-- Table --}}
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-100 dark:border-white/10">
                                            <th class="pb-3 text-left">
                                                <input
                                                    type="checkbox"
                                                    @change="toggleSelectAll($event)"
                                                    :checked="isAllSelected"
                                                    class="h-4 w-4 rounded border-slate-300"
                                                >
                                            </th>
                                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama</th>
                                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Lokasi</th>
                                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">IP</th>
                                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">PON Info</th>
                                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                                            <th class="pb-3 text-right font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="item in items" :key="item.id">
                                            <tr class="border-b border-slate-50 dark:border-white/5 hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                                                <td class="py-3">
                                                    <input
                                                        type="checkbox"
                                                        :value="item.id"
                                                        x-model="selected"
                                                        class="h-4 w-4 rounded border-slate-300"
                                                    >
                                                </td>
                                                <td class="py-3">
                                                    <div class="font-bold text-slate-900 dark:text-white" x-text="item.olt_name ?? item.name ?? '-'"></div>
                                                    <div class="font-mono text-xs text-slate-400" x-text="item.olt_code ?? item.code ?? '-'"></div>
                                                </td>
                                                <td class="py-3 text-slate-600 dark:text-slate-400" x-text="item.location_name ?? '-'"></td>
                                                <td class="py-3 font-mono text-xs text-slate-500" x-text="item.mgmt_ip ?? item.host ?? '-'"></td>
                                                <td class="py-3">
                                                    <div x-data="{ ponStatus: null, loading: false }" x-init="
                                                        async function loadPonStatus() {
                                                            loading = true;
                                                            try {
                                                                const res = await fetch(`/api/v1/admin/olts/${item.id}/pon-status`);
                                                                if (res.ok) {
                                                                    ponStatus = await res.json();
                                                                }
                                                            } finally {
                                                                loading = false;
                                                            }
                                                        }
                                                        loadPonStatus();
                                                    ">
                                                        <template x-if="loading">
                                                            <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                                                                <svg class="h-3 w-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                                </svg>
                                                                Loading
                                                            </span>
                                                        </template>
                                                        <template x-if="!loading && ponStatus">
                                                            <div class="flex items-center gap-2">
                                                                <span class="text-xs font-bold text-slate-600 dark:text-slate-400" x-text="ponStatus.pon_info"></span>
                                                                <template x-if="ponStatus.has_full">
                                                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-black text-red-600 dark:bg-red-500/20 dark:text-red-300">1 PON Penuh</span>
                                                                </template>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </td>
                                                <td class="py-3">
                                                    <span
                                                        class="rounded-full px-2.5 py-1 text-xs font-black uppercase"
                                                        :class="item.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400'"
                                                        x-text="item.is_active ? 'Aktif' : 'Nonaktif'"
                                                    ></span>
                                                </td>
                                                <td class="py-3 text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button
                                                            @click="openPonModal(item)"
                                                            class="rounded-lg border border-slate-200 bg-white p-1.5 text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600 dark:border-white/10 dark:text-slate-400"
                                                            title="Detail PON"
                                                        >
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                                            </svg>
                                                        </button>
                                                        <button
                                                            @click="editItem(item)"
                                                            class="rounded-lg border border-slate-200 bg-white p-1.5 text-slate-600 transition hover:border-blue-200 hover:text-blue-600 dark:border-white/10 dark:text-slate-400"
                                                            title="Edit"
                                                        >
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                            </svg>
                                                        </button>
                                                        <button
                                                            @click="deleteItem(item)"
                                                            class="rounded-lg border border-slate-200 bg-white p-1.5 text-slate-600 transition hover:border-red-200 hover:text-red-600 dark:border-white/10 dark:text-slate-400"
                                                            title="Hapus"
                                                        >
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr x-show="loading">
                                            <td colspan="7" class="py-8 text-center text-slate-400">
                                                <svg class="mx-auto h-6 w-6 animate-spin text-slate-300" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </td>
                                        </tr>
                                        <tr x-show="!loading && items.length === 0">
                                            <td colspan="7" class="py-8 text-center text-slate-400">Belum ada data OLT</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            {{-- Pagination --}}
                            <div class="mt-4 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-slate-500">Tampilkan</span>
                                    <select
                                        x-model="filters.per_page"
                                        @change="changePerPage()"
                                        class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs dark:border-white/10 dark:bg-white/[0.04]"
                                    >
                                        <option value="20">20</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                        <option value="500">500</option>
                                    </select>
                                    <span class="text-xs text-slate-500">per halaman</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button
                                        @click="prevPage()"
                                        :disabled="pagination.current_page <= 1"
                                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                                    >
                                        Prev
                                    </button>
                                    <template x-for="p in visiblePages" :key="p">
                                        <button
                                            @click="goToPage(p)"
                                            :class="p === pagination.current_page ? 'bg-blue-600 text-white' : 'bg-white text-slate-700 dark:bg-white/[0.04] dark:text-slate-300'"
                                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold dark:border-white/10"
                                        >
                                            <span x-text="p"></span>
                                        </button>
                                    </template>
                                    <button
                                        @click="nextPage()"
                                        :disabled="pagination.current_page >= pagination.last_page"
                                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                                    >
                                        Next
                                    </button>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </main>
        </div>
    </div>

    {{-- PON Port Modal --}}
    <div
        x-show="ponModal.show"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @keydown.escape.window="ponModal.show = false"
    >
        <div
            x-show="ponModal.show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-white/10 dark:bg-[#0f172a]"
        >
            {{-- Modal Header --}}
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-4 dark:border-white/10 dark:bg-[#0f172a]">
                <div>
                    <h3 class="text-lg font-black text-slate-950 dark:text-white">PON Port</h3>
                    <p class="text-sm text-slate-500" x-text="ponModal.olt ? ((ponModal.olt.olt_name ?? ponModal.olt.name) + ' (' + (ponModal.olt.location_name ?? '-') + ')') : ''"></p>
                </div>
                <button
                    @click="ponModal.show = false"
                    class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-white/10"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Modal Body --}}
            <div class="p-6">
                {{-- Add PON Port Button --}}
                <div class="mb-4 flex justify-end">
                    <button
                        @click="openAddPonForm()"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-indigo-600/20"
                    >
                        + Tambah PON Port
                    </button>
                </div>

                {{-- Add PON Form (hidden by default) --}}
                <div x-show="ponModal.showAddForm" x-cloak class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-500/30 dark:bg-indigo-500/10">
                    <h4 class="mb-3 text-sm font-black text-slate-700 dark:text-slate-200">Tambah PON Port Baru</h4>
                    <form @submit.prevent="submitPonPort()">
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <label class="block">
                                <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Port Number *</span>
                                <input
                                    type="number"
                                    x-model="ponForm.port_number"
                                    min="1"
                                    max="32"
                                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-indigo-300 focus:ring-4 focus:ring-indigo-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100"
                                    required
                                >
                            </label>
                            <label class="block">
                                <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama</span>
                                <input
                                    type="text"
                                    x-model="ponForm.name"
                                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-indigo-300 focus:ring-4 focus:ring-indigo-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100"
                                    placeholder="PON-1"
                                >
                            </label>
                            <label class="block">
                                <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Max Capacity</span>
                                <input
                                    type="number"
                                    x-model="ponForm.max_capacity"
                                    min="1"
                                    max="1000"
                                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-indigo-300 focus:ring-4 focus:ring-indigo-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100"
                                    placeholder="100"
                                >
                            </label>
                            <label class="flex items-center gap-3 pt-6">
                                <input
                                    type="checkbox"
                                    x-model="ponForm.is_active"
                                    class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                >
                                <span class="text-sm font-bold text-slate-700 dark:text-slate-300">Aktif</span>
                            </label>
                        </div>
                        <div class="mt-4 flex items-center gap-3">
                            <button
                                type="submit"
                                :disabled="ponModal.saving"
                                class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <span x-show="!ponModal.saving">Simpan</span>
                                <span x-show="ponModal.saving">Menyimpan...</span>
                            </button>
                            <button
                                type="button"
                                @click="ponModal.showAddForm = false"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                            >
                                Batal
                            </button>
                        </div>
                    </form>
                </div>

                {{-- PON Ports Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-white/10">
                                <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Port</th>
                                <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama</th>
                                <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Max</th>
                                <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Terpakai</th>
                                <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Sisa</th>
                                <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                                <th class="pb-3 text-right font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="pon in ponModal.ponPorts" :key="pon.id">
                                <tr class="border-b border-slate-50 dark:border-white/5">
                                    <td class="py-3 font-mono font-bold text-slate-700 dark:text-slate-300" x-text="'PON-' + pon.port_number"></td>
                                    <td class="py-3 text-slate-600 dark:text-slate-400" x-text="pon.name ?? '-'"></td>
                                    <td class="py-3 text-center font-mono text-slate-500" x-text="pon.max_capacity"></td>
                                    <td class="py-3 text-center font-mono text-slate-500" x-text="pon.current_count"></td>
                                    <td class="py-3 text-center font-mono" :class="pon.sisa <= 0 ? 'text-red-500 font-bold' : pon.sisa <= 20 ? 'text-amber-500' : 'text-emerald-500'" x-text="pon.sisa"></td>
                                    <td class="py-3">
                                        <template x-if="!pon.is_active">
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-black uppercase text-slate-500 dark:bg-white/10 dark:text-slate-400">Nonaktif</span>
                                        </template>
                                        <template x-if="pon.is_active && pon.status === 'full'">
                                            <span class="rounded-full bg-red-100 px-2.5 py-1 text-xs font-black uppercase text-red-600 dark:bg-red-500/20 dark:text-red-300">PENUH</span>
                                        </template>
                                        <template x-if="pon.is_active && pon.status === 'almost_full'">
                                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-black uppercase text-amber-600 dark:bg-amber-500/20 dark:text-amber-300">Hampir</span>
                                        </template>
                                        <template x-if="pon.is_active && pon.status === 'normal'">
                                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black uppercase text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300">Normal</span>
                                        </template>
                                    </td>
                                    <td class="py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <template x-if="!pon.is_active">
                                                <button
                                                    @click="activatePonPort(pon)"
                                                    class="rounded-lg bg-emerald-500 px-2 py-1 text-xs font-bold text-white"
                                                >
                                                    Aktifkan
                                                </button>
                                            </template>
                                            <template x-if="pon.is_active">
                                                <button
                                                    @click="editPonPort(pon)"
                                                    class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-bold text-slate-700 dark:border-white/10 dark:text-slate-300"
                                                >
                                                    Edit
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="ponModal.loading">
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    <svg class="mx-auto h-6 w-6 animate-spin text-slate-300" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <x-modal />
    <x-toast-notification />
@endsection