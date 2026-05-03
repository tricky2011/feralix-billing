@extends('layouts.app')

@section('title', 'Master Lokasi - Feralix Billing')

@section('content')
<div
    x-data="masterLokasi()"
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

                    <h1 class="mb-6 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Master Lokasi</h1>

                    {{-- Split Layout: Form Kiri 40% | Daftar Kanan 60% --}}
                    <div class="grid gap-6 lg:grid-cols-[2fr_3fr]">
                        {{-- Form Kiri --}}
                        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                            <h2 class="mb-5 text-lg font-black tracking-tight text-slate-950 dark:text-white">
                                <span x-text="editId ? 'Edit Lokasi' : 'Tambah Lokasi Baru'"></span>
                            </h2>

                            <form @submit.prevent="submitForm">
                                <div class="space-y-4">
                                    <label class="block">
                                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama Lokasi *</span>
                                        <input
                                            type="text"
                                            x-model="form.name"
                                            @input="form.name = $event.target.value.toUpperCase()"
                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                            placeholder="KALISARI"
                                            required
                                        >
                                    </label>

                                    <label class="block">
                                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Kode Lokasi *</span>
                                        <input
                                            type="text"
                                            x-model="form.code"
                                            @input="form.code = $event.target.value.toUpperCase().replace(/\s/g, '')"
                                            maxlength="10"
                                            class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm uppercase outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                            placeholder="KLS01"
                                            required
                                        >
                                        <p class="mt-1 text-xs text-slate-400">Dipakai sebagai komponen PPPoE username. Maks 10 karakter, tanpa spasi.</p>
                                    </label>

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
                                        <span x-show="!saving">Simpan Lokasi</span>
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
                                    Daftar Lokasi <span class="ml-2 text-sm font-normal text-slate-400">| Total: <span x-text="pagination.total ?? 0"></span> data</span>
                                </h2>
                            </div>

                            {{-- Search & Filters --}}
                            <div class="mb-4 flex items-center gap-3">
                                <div class="relative flex-1">
                                    <input
                                        type="text"
                                        x-model="filters.search"
                                        @input="debounceSearch()"
                                        placeholder="Cari lokasi..."
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
                                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Kode</th>
                                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Deskripsi</th>
                                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Koordinat</th>
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
                                                <td class="py-3 font-bold text-slate-900 dark:text-white" x-text="item.location_name ?? item.name ?? '-'"></td>
                                                <td class="py-3 font-mono text-slate-600 dark:text-slate-400" x-text="item.location_code ?? item.code ?? '-'"></td>
                                                <td class="py-3 text-slate-500" x-text="item.description ?? item.notes ?? '-'"></td>
                                                <td class="py-3 font-mono text-xs text-slate-400" x-text="formatCoordinates(item.latitude, item.longitude)"></td>
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
                                            <td colspan="7" class="py-8 text-center text-slate-400">Belum ada data lokasi</td>
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

    <x-modal />
    <x-toast-notification />
@endsection