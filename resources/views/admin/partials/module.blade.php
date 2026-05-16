<div class="space-y-5">
    {{-- Master Lokasi Page (Split Layout: Form Kiri | Daftar Kanan) --}}
    <section x-show="page === 'master-lokasi'" x-cloak class="grid gap-6 lg:grid-cols-[2fr_3fr]">
        {{-- Form Kiri --}}
        <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
            <h2 class="mb-5 text-lg font-black tracking-tight text-slate-950 dark:text-white">
                <span x-text="masterLokasi.editId ? 'Edit Lokasi' : 'Tambah Lokasi Baru'"></span>
            </h2>
            <form @submit.prevent="submitMasterLokasiForm">
                <div class="space-y-4">
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama Lokasi *</span>
                        <input type="text" x-model="masterLokasi.form.name" @input="masterLokasi.form.name = $event.target.value.toUpperCase()" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" placeholder="KALISARI" required>
                    </label>
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Kode Lokasi *</span>
                        <input type="text" x-model="masterLokasi.form.code" @input="masterLokasi.form.code = $event.target.value.toUpperCase().replace(/\s/g, '')" maxlength="10" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm uppercase outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" placeholder="KLS01" required>
                        <p class="mt-1 text-xs text-slate-400">Dipakai sebagai komponen PPPoE username. Maks 10 karakter, tanpa spasi.</p>
                    </label>
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Router *</span>
                        <select x-model="masterLokasi.form.router_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" required>
                            <option value="">Pilih Router...</option>
                            <template x-for="router in references.routers" :key="router.id">
                                <option :value="router.id" x-text="(router.router_code ?? '') + ' - ' + (router.router_name ?? '')"></option>
                            </template>
                        </select>
                    </label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Latitude</span>
                            <input type="text" x-model="masterLokasi.form.latitude" @input="masterLokasi.updateMapsLink()" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" placeholder="-6.9123">
                        </label>
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Longitude</span>
                            <input type="text" x-model="masterLokasi.form.longitude" @input="masterLokasi.updateMapsLink()" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" placeholder="107.6098">
                        </label>
                    </div>
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Deskripsi</span>
                        <textarea x-model="masterLokasi.form.description" rows="2" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" placeholder="Deskripsi opsional..."></textarea>
                    </label>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" x-model="masterLokasi.form.is_active" class="h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm font-bold text-slate-700 dark:text-slate-300">Aktif</span>
                    </label>
                </div>
                <div class="mt-6 flex items-center gap-3">
                    <button type="submit" :disabled="masterLokasi.saving" class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/20 disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="!masterLokasi.saving">Simpan Lokasi</span>
                        <span x-show="masterLokasi.saving">Menyimpan...</span>
                    </button>
                    <button type="button" x-show="masterLokasi.editId" @click="masterLokasi.cancelEdit()" class="rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-bold text-slate-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300">Batal</button>
                </div>
            </form>
        </div>
        {{-- Daftar Kanan --}}
        <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg font-black tracking-tight text-slate-950 dark:text-white">Daftar Lokasi <span class="ml-2 text-sm font-normal text-slate-400">| Total: <span x-text="masterLokasi.pagination.total ?? 0"></span> data</span></h2>
            </div>
            <div class="mb-4 flex items-center gap-3">
                <div class="relative flex-1">
                    <input type="text" x-model="masterLokasi.filters.search" @input="masterLokasi.debounceSearch()" placeholder="Cari lokasi..." class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 pl-10 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <button @click="masterLokasi.resetFilters()" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300">Reset</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-white/10">
                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama</th>
                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Kode</th>
                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Router</th>
                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                            <th class="pb-3 text-right font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="item in masterLokasi.items" :key="item.id">
                            <tr class="border-b border-slate-50 dark:border-white/5 hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                                <td class="py-3 font-bold text-slate-900 dark:text-white" x-text="item.location_name ?? item.name ?? '-'"></td>
                                <td class="py-3 font-mono text-slate-600 dark:text-slate-400" x-text="item.location_code ?? item.code ?? '-'"></td>
                                <td class="py-3">
                                    <template x-if="item.router_id">
                                        <span class="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-black text-blue-700 dark:bg-blue-500/20 dark:text-blue-300" x-text="references.routers.find(r => r.id == item.router_id)?.router_code ?? item.router_id"></span>
                                    </template>
                                    <template x-if="!item.router_id">
                                        <span class="text-xs text-slate-400">-</span>
                                    </template>
                                </td>
                                <td class="py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-black uppercase" :class="item.status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400'" x-text="item.status === 'active' ? 'Aktif' : 'Nonaktif'"></span>
                                </td>
                                <td class="py-3 text-right">
                                    <button @click="masterLokasi.editItem(item)" class="rounded-lg border border-slate-200 bg-white p-1.5 text-slate-600 transition hover:border-blue-200 hover:text-blue-600 dark:border-white/10 dark:text-slate-400" title="Edit">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button @click="masterLokasi.deleteItem(item)" class="rounded-lg border border-slate-200 bg-white p-1.5 text-slate-600 transition hover:border-red-200 hover:text-red-600 dark:border-white/10 dark:text-slate-400" title="Hapus">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="masterLokasi.loading">
                            <td colspan="5" class="py-8 text-center text-slate-400">Memuat...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500">Tampilkan</span>
                    <select x-model="masterLokasi.filters.per_page" @change="masterLokasi.changePerPage()" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs dark:border-white/10 dark:bg-white/[0.04]">
                        <option value="20">20</option><option value="50">50</option><option value="100">100</option><option value="500">500</option>
                    </select>
                </div>
                <div class="flex items-center gap-1">
                    <button @click="masterLokasi.prevPage()" :disabled="masterLokasi.pagination.current_page <= 1" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300">Prev</button>
                    <button @click="masterLokasi.nextPage()" :disabled="masterLokasi.pagination.current_page >= masterLokasi.pagination.last_page" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300">Next</button>
                </div>
            </div>
        </div>
    </section>

    {{-- Master OLT Page (Split Layout: Form Kiri | Daftar Kanan) --}}
    <section x-show="page === 'master-olt'" x-cloak class="grid gap-6 lg:grid-cols-[2fr_3fr]">
        {{-- Form Kiri --}}
        <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
            <h2 class="mb-5 text-lg font-black tracking-tight text-slate-950 dark:text-white">
                <span x-text="masterOlt.editId ? 'Edit OLT' : 'Tambah OLT Baru'"></span>
            </h2>
            <form @submit.prevent="masterOlt.submitForm()">
                <div class="space-y-4">
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama OLT *</span>
                        <input type="text" x-model="masterOlt.form.name" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" placeholder="OLT1" required>
                    </label>
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Kode OLT *</span>
                        <input type="text" x-model="masterOlt.form.code" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm uppercase outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" placeholder="OLT01" required>
                        <p class="mt-1 text-xs text-slate-400">Dipakai di PPPoE username.</p>
                    </label>
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Lokasi *</span>
                        <select x-model="masterOlt.form.network_location_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" required>
                            <option value="">Pilih Lokasi...</option>
                            <template x-for="loc in references.network_locations" :key="loc.id">
                                <option :value="loc.id" x-text="(loc.code ?? '') + ' - ' + (loc.name ?? '')"></option>
                            </template>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Router</span>
                        <select x-model="masterOlt.form.router_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10">
                            <option value="">Pilih Router...</option>
                            <template x-for="router in references.routers" :key="router.id">
                                <option :value="router.id" x-text="(router.router_code ?? '') + ' - ' + (router.router_name ?? '')"></option>
                            </template>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">IP Address</span>
                        <input type="text" x-model="masterOlt.form.host" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" placeholder="192.168.1.1">
                    </label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Total PON Port</span>
                            <input type="number" x-model="masterOlt.form.pon_ports" min="1" max="32" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" placeholder="4">
                        </label>
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Max Client/PON</span>
                            <input type="number" x-model="masterOlt.form.max_per_pon" min="1" max="1000" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" placeholder="100">
                        </label>
                    </div>
                    <label class="block">
                        <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Deskripsi</span>
                        <textarea x-model="masterOlt.form.description" rows="2" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" placeholder="Deskripsi opsional..."></textarea>
                    </label>
                    <label class="flex items-center gap-3">
                        <input type="checkbox" x-model="masterOlt.form.is_active" class="h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm font-bold text-slate-700 dark:text-slate-300">Aktif</span>
                    </label>
                </div>
                <div class="mt-6 flex items-center gap-3">
                    <button type="submit" :disabled="masterOlt.saving" class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/20 disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="!masterOlt.saving">Simpan OLT</span>
                        <span x-show="masterOlt.saving">Menyimpan...</span>
                    </button>
                    <button type="button" x-show="masterOlt.editId" @click="masterOlt.cancelEdit()" class="rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-bold text-slate-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300">Batal</button>
                </div>
            </form>
        </div>
        {{-- Daftar Kanan --}}
        <div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg font-black tracking-tight text-slate-950 dark:text-white">Daftar OLT <span class="ml-2 text-sm font-normal text-slate-400">| Total: <span x-text="masterOlt.pagination.total ?? 0"></span> data</span></h2>
            </div>
            <div class="mb-4 flex items-center gap-3">
                <div class="relative flex-1">
                    <input type="text" x-model="masterOlt.filters.search" @input="masterOlt.debounceSearch()" placeholder="Cari OLT..." class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 pl-10 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <button @click="masterOlt.resetFilters()" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300">Reset</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-white/10">
                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama</th>
                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Lokasi</th>
                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">IP</th>
                            <th class="pb-3 text-left font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Status</th>
                            <th class="pb-3 text-right font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="item in masterOlt.items" :key="item.id">
                            <tr class="border-b border-slate-50 dark:border-white/5 hover:bg-slate-50 dark:hover:bg-white/[0.02]">
                                <td class="py-3">
                                    <div class="font-bold text-slate-900 dark:text-white" x-text="item.olt_name ?? item.name ?? '-'"></div>
                                    <div class="font-mono text-xs text-slate-400" x-text="item.olt_code ?? item.code ?? '-'"></div>
                                </td>
                                <td class="py-3 text-xs text-slate-500" x-text="item.network_location?.name ?? item.location_name ?? '-'"></td>
                                <td class="py-3 font-mono text-xs text-slate-500" x-text="item.mgmt_ip ?? item.host ?? '-'"></td>
                                <td class="py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-black uppercase" :class="item.status === 'active' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400'" x-text="item.status === 'active' ? 'Aktif' : 'Nonaktif'"></span>
                                </td>
                                <td class="py-3 text-right">
                                    <button @click="masterOlt.openPonModal(item)" class="rounded-lg border border-slate-200 bg-white p-1.5 text-slate-600 transition hover:border-indigo-200 hover:text-indigo-600 dark:border-white/10 dark:text-slate-400" title="Detail PON">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                    </button>
                                    <button @click="masterOlt.editItem(item)" class="rounded-lg border border-slate-200 bg-white p-1.5 text-slate-600 transition hover:border-blue-200 hover:text-blue-600 dark:border-white/10 dark:text-slate-400" title="Edit">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button @click="masterOlt.deleteItem(item)" class="rounded-lg border border-slate-200 bg-white p-1.5 text-slate-600 transition hover:border-red-200 hover:text-red-600 dark:border-white/10 dark:text-slate-400" title="Hapus">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="masterOlt.loading">
                            <td colspan="5" class="py-8 text-center text-slate-400">Memuat...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500">Tampilkan</span>
                    <select x-model="masterOlt.filters.per_page" @change="masterOlt.changePerPage()" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs dark:border-white/10 dark:bg-white/[0.04]">
                        <option value="20">20</option><option value="50">50</option><option value="100">100</option><option value="500">500</option>
                    </select>
                </div>
                <div class="flex items-center gap-1">
                    <button @click="masterOlt.prevPage()" :disabled="masterOlt.pagination.current_page <= 1" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300">Prev</button>
                    <button @click="masterOlt.nextPage()" :disabled="masterOlt.pagination.current_page >= masterOlt.pagination.last_page" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300">Next</button>
                </div>
            </div>
        </div>
    </section>

    {{-- Customer Create Form --}}
    <section x-show="page === 'customers-create'" x-cloak class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
        <div class="mb-5 flex items-center justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.24em] text-blue-700 dark:text-blue-300">CRM</p>
                <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-white">Tambah Pelanggan Baru</h2>
            </div>
            <span class="rounded-full bg-amber-100 px-4 py-2 text-xs font-black uppercase tracking-wide text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
                PREPAID - BAYAR DULU AKTIF
            </span>
        </div>

        {{-- Prepaid Info Banner --}}
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-500/10">
            <p class="text-sm font-bold text-amber-800 dark:text-amber-200">Alur Prepaid:</p>
            <ol class="mt-1.5 ml-4 list-inside list-decimal text-xs text-amber-700 dark:text-amber-300 space-y-0.5">
                <li>Customer baru dibuat → PPPoE <strong>disabled</strong> di Mikrotik</li>
                <li>Invoice pertama langsung di-generate saat customer dibuat</li>
                <li>Admin konfirmasi pembayaran → PPPoE <strong>enabled</strong> → service <strong>active</strong></li>
            </ol>
        </div>

        <form @submit.prevent="submitProvisioningForm">
            <div class="space-y-5">
                {{-- Section 1: Data Pelanggan --}}
                <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4 dark:border-white/10 dark:bg-white/[0.02]">
                    <h3 class="mb-4 text-sm font-black uppercase tracking-wide text-slate-600 dark:text-slate-400">Data Pelanggan</h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama Pelanggan *</span>
                            <input
                                type="text"
                                x-model="provisioning.form.full_name"
                                @input="provisioning.form.full_name = $event.target.value.toUpperCase()"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                placeholder="Nama lengkap"
                                required
                            >
                            <p x-show="provisioning.errors.full_name" x-text="provisioning.errors.full_name" class="mt-1 text-xs text-red-600"></p>
                        </label>

                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Lokasi</span>
                            <select
                                x-model="provisioning.form.location_id"
                                @change="onProvisioningLocationChange()"
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
                                x-model="provisioning.form.olt_id"
                                @change="onProvisioningOltChange()"
                                :disabled="!provisioning.form.location_id"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                            >
                                <option value="">Pilih OLT...</option>
                                <template x-for="olt in provisioningFilteredOlts()" :key="olt.id">
                                    <option :value="olt.id" x-text="(olt.olt_code ?? olt.code ?? '') + ' - ' + (olt.olt_name ?? olt.name ?? '')"></option>
                                </template>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Assign Teknisi</span>
                            <select
                                x-model="provisioning.form.assigned_technician_id"
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
                            <select
                                x-model="provisioning.form.router_id"
                                @change="onProvisioningRouterChange()"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                            >
                                <option value="">Pilih Router...</option>
                                <template x-for="router in references.routers" :key="router.id">
                                    <option :value="router.id" x-text="(router.router_code ?? '') + ' - ' + (router.router_name ?? '')"></option>
                                </template>
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">No HP</span>
                            <input
                                type="tel"
                                x-model="provisioning.form.phone"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                placeholder="08xxxxxxxxxx"
                            >
                        </label>

                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Kontak</span>
                            <input
                                type="text"
                                x-model="provisioning.form.contact"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                placeholder="WhatsApp / Telegram"
                            >
                        </label>

                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Email</span>
                            <input
                                type="email"
                                x-model="provisioning.form.email"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                placeholder="email@example.com"
                            >
                        </label>
                    </div>

                    <div class="mt-4">
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Alamat</span>
                            <textarea
                                x-model="provisioning.form.address"
                                rows="2"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                placeholder="Alamat lengkap pelanggan"
                            ></textarea>
                        </label>
                    </div>
                </div>

                {{-- Section 2: Data Instalasi --}}
                <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4 dark:border-white/10 dark:bg-white/[0.02]">
                    <h3 class="mb-4 text-sm font-black uppercase tracking-wide text-slate-600 dark:text-slate-400">Data Instalasi</h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Tanggal Instalasi</span>
                            <input
                                type="date"
                                x-model="provisioning.form.install_date"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                            >
                        </label>

                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Latitude</span>
                            <input
                                type="text"
                                x-model="provisioning.form.latitude"
                                @input="onProvisioningLatLngChange()"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                placeholder="-6.9xxxxxx"
                            >
                        </label>

                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Longitude</span>
                            <input
                                type="text"
                                x-model="provisioning.form.longitude"
                                @input="onProvisioningLatLngChange()"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                placeholder="107.6xxxxxx"
                            >
                        </label>

                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Link Google Maps</span>
                            <input
                                type="url"
                                x-model="provisioning.form.maps_link"
                                readonly
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm outline-none dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                                placeholder="Auto-generate"
                            >
                        </label>
                    </div>
                </div>

                {{-- Section 3: Mode Layanan & Koneksi --}}
                <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4 dark:border-white/10 dark:bg-white/[0.02]">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-black uppercase tracking-wide text-slate-600 dark:text-slate-400">Mode Layanan & Koneksi</h3>
                        <button
                            type="button"
                            @click="generateProvisioningCredentials()"
                            :disabled="!canGenerateProvisioningCredentials()"
                            class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-blue-600/20 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Generate Username & Password
                        </button>
                    </div>

                    <div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50/70 px-4 py-2 dark:border-indigo-500/30 dark:bg-indigo-500/10">
                        <p class="text-sm font-bold text-indigo-800 dark:text-indigo-200">
                            Mode: <span class="uppercase">PPPoE</span>
                            <span class="ml-2 text-xs font-normal text-indigo-600 dark:text-indigo-400">(fixed)</span>
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Username PPP *</span>
                            <input
                                type="text"
                                x-model="provisioning.form.pppoe_username"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-mono uppercase outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                placeholder="NAMA-KODELOKASI-KODEOLT"
                                required
                            >
                            <p x-show="provisioning.errors.pppoe_username" x-text="provisioning.errors.pppoe_username" class="mt-1 text-xs text-red-600"></p>
                        </label>

                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Password PPP *</span>
                            <input
                                type="text"
                                x-model="provisioning.form.pppoe_password"
                                readonly
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm font-mono outline-none dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                                placeholder="ddmmyyyy"
                            >
                        </label>

                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">VID PPPoE Server</span>
                            <select
                                x-model="provisioning.form.pppoe_server"
                                :disabled="!provisioning.form.router_id || provisioning.loadingPppoeServers"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                            >
                                <option value="">Pilih PPPoE Server...</option>
                                <template x-for="server in provisioning.pppoeServers" :key="server.name">
                                    <option :value="server.name" x-text="server.label"></option>
                                </template>
                            </select>
                            <p x-show="provisioning.loadingPppoeServers" class="mt-1 text-xs text-slate-500">Memuat...</p>
                        </label>

                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">VID Pelanggan</span>
                            <div class="mt-2 flex items-center gap-2">
                                <input
                                    type="text"
                                    :value="provisioningAssignedVid()"
                                    readonly
                                    class="w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm font-mono outline-none dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                                    placeholder="V-XXXX"
                                >
                                <button
                                    type="button"
                                    @click="refreshProvisioningAssignedVid()"
                                    :disabled="!provisioning.form.router_id || provisioning.loadingAssignedVid"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-bold text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:text-slate-200"
                                    title="Refresh VID"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                </button>
                            </div>
                            <p x-show="!provisioningAssignedVid() && !provisioning.loadingAssignedVid && provisioning.form.router_id" class="mt-1 text-xs text-red-600">Tidak ada VID tersedia</p>
                        </label>

                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">IP Address</span>
                            <input
                                type="text"
                                :value="provisioningAssignedIpAddress()"
                                readonly
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm font-mono outline-none dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                                placeholder="Auto"
                            >
                        </label>

                        <label class="block">
                            <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Harga Bulanan (Rp)</span>
                            <input
                                type="number"
                                x-model="provisioning.form.monthly_price"
                                min="0"
                                step="1000"
                                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                                placeholder="150000"
                            >
                            <p class="mt-1 text-xs text-slate-400">Harga layanan internet per bulan</p>
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
                </div>

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
                        :disabled="provisioning.saving"
                        class="rounded-xl bg-blue-600 px-8 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/20 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span x-show="!provisioning.saving">Simpan Pelanggan</span>
                        <span x-show="provisioning.saving">Menyimpan...</span>
                    </button>
                </div>
            </div>
        </form>
    </section>

    <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.24em] text-blue-700 dark:text-blue-300" x-text="currentSectionLabel()"></p>
                <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-white" x-text="currentTitle()"></h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400" x-text="currentDescription()"></p>
            </div>
            <div x-show="!isPlaceholderCurrent() && page !== 'technician-dashboard' && page !== 'router-sync' && page !== 'fiber-network-map'" class="flex flex-col gap-3 sm:flex-row">
                <input
                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-blue-500/10"
                    type="search"
                    placeholder="Cari data..."
                    x-model.debounce.500ms="filters.search"
                    @input="changePage(1)"
                >
                <button
                    type="button"
                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-blue-200 hover:text-blue-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300 dark:hover:text-white"
                    @click="loadPage"
                >
                    Refresh
                </button>
                <button
                    x-show="page === 'customers'"
                    type="button"
                    class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/20"
                    onclick="window.location.href='/admin/customers/create'"
                >
                    Tambah
                </button>
                <button
                    x-show="page !== 'customers'"
                    type="button"
                    class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/20 disabled:hidden"
                    @click="openCreate"
                    :disabled="!canCreateCurrent()"
                >
                    Tambah
                </button>
            </div>
        </div>

        <div x-show="hasTabs() && !isPlaceholderCurrent() && page !== 'technician-dashboard' && page !== 'router-sync' && page !== 'fiber-network-map'" class="mt-5 flex flex-wrap gap-2">
            <template x-for="tab in currentTabs()" :key="tab.key">
                <button
                    type="button"
                    class="rounded-2xl px-4 py-2 text-sm font-bold transition"
                    :class="activeTab === tab.key ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/[0.05] dark:text-slate-300 dark:hover:bg-white/[0.08]'"
                    @click="selectTab(tab.key)"
                    x-text="tab.label"
                ></button>
            </template>
        </div>

        <div x-show="page === 'cashflow' && !isPlaceholderCurrent()" x-cloak class="mt-5 grid gap-3 rounded-3xl border border-slate-200 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-[#07111f]/70 lg:grid-cols-5">
            <label class="block">
                <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Type</span>
                <select class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" x-model="cashflow.filters.type">
                    <template x-for="option in cashflowTypeOptions()" :key="`type-${option.value}`">
                        <option :value="option.value" x-text="option.label"></option>
                    </template>
                </select>
            </label>

            <label class="block">
                <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Category</span>
                <select class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" x-model="cashflow.filters.category_id">
                    <template x-for="option in cashflowCategoryOptions()" :key="`category-${option.value}`">
                        <option :value="option.value" x-text="option.label"></option>
                    </template>
                </select>
            </label>

            <label class="block">
                <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Date from</span>
                <input class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" type="date" x-model="cashflow.filters.date_from">
            </label>

            <label class="block">
                <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Date to</span>
                <input class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" type="date" x-model="cashflow.filters.date_to">
            </label>

            <div class="flex items-end gap-2">
                <button type="button" class="w-full rounded-xl bg-blue-600 px-4 py-2 text-sm font-black text-white shadow-lg shadow-blue-600/20" @click="applyCashflowFilters">Apply</button>
                <button type="button" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-black text-slate-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-200" @click="resetCashflowFilters">Reset</button>
            </div>
        </div>
    </section>

    <section x-show="page === 'fiber-network-map' && !isPlaceholderCurrent()" x-cloak class="space-y-5 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <template x-for="card in fiberMapSummaryCards()" :key="card.key">
                <article class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5 dark:border-white/10 dark:bg-[#07111f]/70">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400" x-text="card.label"></p>
                    <p class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white" x-text="card.value"></p>
                </article>
            </template>
        </div>

        <article class="rounded-3xl border border-emerald-200 bg-emerald-50/80 p-5 dark:border-emerald-500/30 dark:bg-emerald-500/10">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-200">Fiber Map Placeholder</p>
            <h3 class="mt-2 text-xl font-black tracking-tight text-emerald-900 dark:text-emerald-100">Visual map belum aktif. Data master jaringan sudah siap.</h3>
            <p class="mt-3 text-sm text-emerald-800/80 dark:text-emerald-200/80">
                Placeholder endpoint aktif untuk menyiapkan node dan edge. Implementasi visual map kompleks akan diaktifkan pada fase berikutnya.
            </p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-emerald-200/70 bg-white/70 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-400/30 dark:bg-white/[0.04] dark:text-emerald-100">
                    Nodes: <span class="font-black" x-text="fiberMap.nodes.length"></span>
                </div>
                <div class="rounded-2xl border border-emerald-200/70 bg-white/70 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-400/30 dark:bg-white/[0.04] dark:text-emerald-100">
                    Edges: <span class="font-black" x-text="fiberMap.edges.length"></span>
                </div>
            </div>
        </article>
    </section>

    <section x-show="page === 'technician-dashboard' && !isPlaceholderCurrent()" x-cloak class="space-y-5">
        <article class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
            <div class="grid gap-3 lg:grid-cols-[1fr_220px_170px_170px_auto]">
                <label class="block">
                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Teknisi</span>
                    <select class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" x-model="technicianDashboard.filters.technician_id">
                        <option value="" x-show="!isTechnician()">Semua teknisi</option>
                        <template x-for="technician in technicianFilterOptions()" :key="`tech-${technician.id}`">
                            <option :value="technician.id" x-text="`${technician.technician_code} - ${technician.full_name}`"></option>
                        </template>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Periode</span>
                    <select class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" x-model="technicianDashboard.filters.period" @change="onTechnicianDashboardPeriodChanged">
                        <template x-for="option in technicianPeriodOptions()" :key="`period-${option.value}`">
                            <option :value="option.value" x-text="option.label"></option>
                        </template>
                    </select>
                </label>

                <label class="block" x-show="technicianDashboard.filters.period === 'custom'">
                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Date from</span>
                    <input class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" type="date" x-model="technicianDashboard.filters.date_from">
                </label>

                <label class="block" x-show="technicianDashboard.filters.period === 'custom'">
                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Date to</span>
                    <input class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" type="date" x-model="technicianDashboard.filters.date_to">
                </label>

                <div class="flex items-end gap-2">
                    <button type="button" class="w-full rounded-xl bg-blue-600 px-4 py-2 text-sm font-black text-white shadow-lg shadow-blue-600/20" @click="applyTechnicianDashboardFilters">Apply</button>
                    <button type="button" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-black text-slate-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-200" @click="resetTechnicianDashboardFilters">Reset</button>
                    <button type="button" class="w-full rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-800 disabled:opacity-50" :disabled="technicianDashboard.exporting" @click="exportTechnicianDashboardPdf">
                        Export PDF
                    </button>
                </div>
            </div>
        </article>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Total instalasi</p>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white" x-text="technicianDashboardSummary().total_installations"></p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Instalasi selesai</p>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white" x-text="technicianDashboardSummary().completed_installations"></p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Tiket open</p>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white" x-text="technicianDashboardSummary().open_tickets"></p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Tiket selesai</p>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white" x-text="technicianDashboardSummary().closed_tickets"></p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Total poin</p>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white" x-text="technicianDashboardSummary().total_points"></p>
            </article>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-black text-slate-900 dark:text-white">Target instalasi</p>
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
                        <span x-text="technicianDashboardTargetProgress('installation').value"></span> /
                        <span x-text="technicianDashboardTargetProgress('installation').target"></span>
                    </span>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
                    <div class="h-full rounded-full bg-blue-500" :style="`width: ${technicianDashboardTargetProgress('installation').percent}%`"></div>
                </div>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-black text-slate-900 dark:text-white">Target tiket</p>
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400">
                        <span x-text="technicianDashboardTargetProgress('ticket').value"></span> /
                        <span x-text="technicianDashboardTargetProgress('ticket').target"></span>
                    </span>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
                    <div class="h-full rounded-full bg-emerald-500" :style="`width: ${technicianDashboardTargetProgress('ticket').percent}%`"></div>
                </div>
            </article>
        </div>

        <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
            <div class="border-b border-slate-100 px-5 py-4 text-sm font-black text-slate-900 dark:border-white/10 dark:text-white">Ranking Teknisi</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-[0.16em] text-slate-500 dark:bg-white/[0.03] dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Rank</th>
                            <th class="px-5 py-3">Teknisi</th>
                            <th class="px-5 py-3">Instalasi selesai</th>
                            <th class="px-5 py-3">Tiket selesai</th>
                            <th class="px-5 py-3">Poin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                        <template x-for="row in technicianDashboardRanking()" :key="`rank-${row.technician_id}`">
                            <tr>
                                <td class="px-5 py-3 font-black text-slate-900 dark:text-white" x-text="row.rank"></td>
                                <td class="px-5 py-3 text-slate-700 dark:text-slate-200" x-text="row.technician_name"></td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.completed_installations"></td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.closed_tickets"></td>
                                <td class="px-5 py-3 font-bold text-blue-700 dark:text-blue-300" x-text="row.points"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div x-show="technicianDashboardRanking().length === 0" class="p-5 text-sm text-slate-500 dark:text-slate-400">Belum ada data ranking untuk filter ini.</div>
        </article>

        <div class="grid gap-5 xl:grid-cols-2">
            <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                <div class="border-b border-slate-100 px-5 py-4 text-sm font-black text-slate-900 dark:border-white/10 dark:text-white">Work order terbaru</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10">
                        <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-[0.16em] text-slate-500 dark:bg-white/[0.03] dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3">WO</th>
                                <th class="px-5 py-3">Teknisi</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Customer</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                            <template x-for="row in technicianDashboardWorkOrders()" :key="`wo-${row.id}`">
                                <tr>
                                    <td class="px-5 py-3 font-bold text-slate-900 dark:text-white" x-text="row.wo_number"></td>
                                    <td class="px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.assigned_technician?.full_name ?? '-'"></td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide" :class="statusClass(row.status)" x-text="formatValue(row.status, { type: 'status' })"></span>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.customer?.full_name ?? '-'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div x-show="technicianDashboardWorkOrders().length === 0" class="p-5 text-sm text-slate-500 dark:text-slate-400">Belum ada work order pada periode ini.</div>
            </article>

            <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                <div class="border-b border-slate-100 px-5 py-4 text-sm font-black text-slate-900 dark:border-white/10 dark:text-white">Ticket terbaru</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10">
                        <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-[0.16em] text-slate-500 dark:bg-white/[0.03] dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3">Ticket</th>
                                <th class="px-5 py-3">Teknisi</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Customer</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                            <template x-for="row in technicianDashboardTickets()" :key="`ticket-${row.id}`">
                                <tr>
                                    <td class="px-5 py-3 font-bold text-slate-900 dark:text-white" x-text="row.ticket_number"></td>
                                    <td class="px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.assigned_technician?.full_name ?? '-'"></td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide" :class="statusClass(row.status)" x-text="formatValue(row.status, { type: 'status' })"></span>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600 dark:text-slate-300" x-text="row.customer?.full_name ?? '-'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div x-show="technicianDashboardTickets().length === 0" class="p-5 text-sm text-slate-500 dark:text-slate-400">Belum ada ticket pada periode ini.</div>
            </article>
        </div>

        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Point rule</p>
            <div class="mt-3 grid gap-2">
                <template x-for="rule in technicianDashboardPointRules()" :key="rule.code">
                    <div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-[#07111f]/70">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200" x-text="rule.label"></span>
                        <span class="text-sm font-black text-blue-700 dark:text-blue-300"><span x-text="rule.points"></span> pts</span>
                    </div>
                </template>
            </div>
        </article>
    </section>

    <section x-show="page === 'cashflow' && !isPlaceholderCurrent()" x-cloak class="grid gap-5 lg:grid-cols-[1fr_1.3fr]">
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
            <template x-for="card in cashflowSummaryCards()" :key="card.key">
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400" x-text="card.label"></p>
                    <p class="mt-3 text-2xl font-black tracking-tight" :class="card.class" x-text="card.value"></p>
                </article>
            </template>
        </div>

        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-700 dark:text-blue-300">Monthly</p>
                    <h3 class="mt-1 text-xl font-black tracking-tight text-slate-950 dark:text-white">Income vs Expense</h3>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 dark:bg-white/[0.08] dark:text-slate-300" x-text="`${cashflowMonthlyPoints().length} bulan`"></span>
            </div>

            <div class="mt-5 flex h-64 items-end gap-2 rounded-2xl border border-slate-100 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-[#07111f]/70">
                <template x-for="point in cashflowMonthlyPoints()" :key="point.month">
                    <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                        <div class="flex h-44 w-full items-end gap-1 rounded-xl bg-white p-1 dark:bg-white/[0.05]">
                            <div class="w-1/2 rounded-t-lg bg-emerald-500" :style="`height: ${barHeight(point.income, cashflowMonthlyMax())}%`"></div>
                            <div class="w-1/2 rounded-t-lg bg-rose-500" :style="`height: ${barHeight(point.expense, cashflowMonthlyMax())}%`"></div>
                        </div>
                        <p class="w-full truncate text-center text-[11px] font-bold text-slate-500 dark:text-slate-400" x-text="point.label"></p>
                    </div>
                </template>
            </div>
        </article>
    </section>

    <section x-show="page === 'config-acs' && !isPlaceholderCurrent()" x-cloak class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
        <div class="grid gap-4 lg:grid-cols-[280px_1fr]">
            <label>
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Pilih Router</span>
                <select
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                    x-model="acsConfig.router_id"
                    @change="selectAcsRouter($event.target.value)"
                >
                    <option value="">Pilih router...</option>
                    <template x-for="router in items" :key="router.id">
                        <option :value="router.id" x-text="(router.name ?? router.router_name ?? ('#' + router.id)) + ' (' + (router.host ?? router.mgmt_ip ?? '-') + ')'"></option>
                    </template>
                </select>
            </label>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-2xl bg-slate-50 p-3 dark:bg-white/[0.04]">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Router</p>
                    <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white" x-text="acsConfig.router_name || '-'"></p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-3 dark:bg-white/[0.04]">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Host</p>
                    <p class="mt-1 text-sm font-bold text-slate-900 dark:text-white" x-text="acsConfig.host || '-'"></p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-3 dark:bg-white/[0.04]">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Status Router</p>
                    <span class="mt-1 inline-flex rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide" :class="statusClass(acsConfig.status)" x-text="formatValue(acsConfig.status, { type: 'status' })"></span>
                </div>
                <div class="rounded-2xl bg-slate-50 p-3 dark:bg-white/[0.04]">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">ACS Password</p>
                    <span class="mt-1 inline-flex rounded-full px-3 py-1 text-xs font-black" :class="acsConfig.has_acs_password ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'" x-text="acsConfig.has_acs_password ? 'Password tersimpan' : 'Password belum ada'"></span>
                </div>
            </div>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">ACS Inform URL</span>
                <input class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" type="url" x-model="acsConfig.acs_inform_url" placeholder="http://acs.local:7547">
            </label>
            <label class="block">
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">ACS NBI URL</span>
                <input class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" type="url" x-model="acsConfig.acs_nbi_url" placeholder="http://acs.local:7557">
            </label>
            <label class="block">
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">ACS Username</span>
                <input class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" type="text" x-model="acsConfig.acs_username" placeholder="Username ACS">
            </label>
            <label class="block">
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">ACS Password</span>
                <input class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10" type="password" x-model="acsConfig.acs_password" placeholder="Kosongkan jika tidak ingin ubah">
            </label>
        </div>

        <div class="mt-5 flex flex-wrap gap-3">
            <button
                type="button"
                class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-bold text-white disabled:opacity-50"
                :disabled="!acsConfig.router_id || acsConfig.saving || acsConfig.loading"
                @click="saveAcsConfig"
            >
                Simpan Config ACS
            </button>
            <button
                type="button"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800 disabled:opacity-50"
                :disabled="!acsConfig.router_id || acsConfig.loading"
                @click="testAcsRouter()"
            >
                Test ACS
            </button>
            <button
                type="button"
                class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-bold text-blue-800 disabled:opacity-50"
                :disabled="!acsConfig.router_id || acsConfig.loading"
                @click="syncOntRouter()"
            >
                Sync ONT Placeholder
            </button>
        </div>

        <div x-show="acsConfig.result" x-cloak class="mt-5 rounded-2xl border px-4 py-3" :class="acsConfig.result?.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800'">
            <p class="text-sm font-bold" x-text="acsConfig.result?.message ?? '-'"></p>
            <p class="mt-1 text-xs" x-text="acsConfig.result?.at ? `Updated: ${formatValue(acsConfig.result.at)}` : ''"></p>
            <pre class="mt-3 overflow-auto rounded-xl bg-white/70 p-3 text-xs text-slate-700" x-text="JSON.stringify(acsConfig.result?.data ?? {}, null, 2)"></pre>
        </div>
    </section>

    <section x-show="page === 'isolations' && !isPlaceholderCurrent()" x-cloak class="space-y-5 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
        <label class="block">
            <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Pilih Router (Wajib)</span>
            <select
                class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                x-model="manualIsolir.router_id"
                @change="onManualRouterChanged()"
            >
                <option value="">Pilih router aktif...</option>
                <template x-for="router in activeManualRouters()" :key="router.id">
                    <option :value="router.id" x-text="manualRouterLabel(router)"></option>
                </template>
            </select>
        </label>

        <div x-show="!manualIsolir.router_id" class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            Pilih router terlebih dahulu untuk menjalankan isolir/release manual.
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-red-200 bg-red-50/70 p-4 dark:border-red-400/20 dark:bg-red-500/10">
                <h3 class="text-lg font-black text-red-800 dark:text-red-200">Manual Isolir User</h3>
                <p class="mt-1 text-xs text-red-700/80 dark:text-red-200/80">Cari user PPPoE atau static lalu jalankan isolir.</p>

                <div class="mt-4 flex gap-2">
                    <input
                        class="w-full rounded-xl border border-red-200 bg-white px-3 py-2 text-sm outline-none focus:border-red-300 focus:ring-4 focus:ring-red-100 dark:border-red-400/20 dark:bg-white/[0.06] dark:text-slate-100 dark:focus:ring-red-500/20"
                        type="search"
                        placeholder="Search user (id / username / static ip)"
                        x-model="manualIsolir.isolir.search"
                        @keyup.enter.prevent="searchManualUsers('isolir')"
                    >
                    <button
                        type="button"
                        class="rounded-xl border border-red-200 bg-white px-3 py-2 text-sm font-bold text-red-700 disabled:opacity-50 dark:border-red-400/30 dark:bg-transparent dark:text-red-200"
                        :disabled="!manualIsolir.router_id || manualIsolir.isolir.loading"
                        @click="searchManualUsers('isolir')"
                    >
                        Cari
                    </button>
                </div>

                <label class="mt-3 block">
                    <span class="text-xs font-semibold uppercase tracking-wide text-red-700 dark:text-red-200">User</span>
                    <select
                        class="mt-2 w-full rounded-xl border border-red-200 bg-white px-3 py-2 text-sm outline-none focus:border-red-300 focus:ring-4 focus:ring-red-100 dark:border-red-400/20 dark:bg-white/[0.06] dark:text-slate-100 dark:focus:ring-red-500/20"
                        x-model="manualIsolir.isolir.user_id"
                    >
                        <option value="">Pilih user...</option>
                        <template x-for="item in manualIsolir.isolir.options" :key="item.service_id">
                            <option :value="item.service_id" x-text="item.label"></option>
                        </template>
                    </select>
                </label>

                <button
                    type="button"
                    class="mt-4 w-full rounded-xl bg-red-600 px-4 py-3 text-sm font-black text-white shadow-lg shadow-red-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canRunManualOperation('isolir')"
                    @click="runManualIsolir"
                >
                    Isolir User
                </button>
            </section>

            <section class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 dark:border-emerald-400/20 dark:bg-emerald-500/10">
                <h3 class="text-lg font-black text-emerald-800 dark:text-emerald-200">Manual Release User</h3>
                <p class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-200/80">Cari user dengan isolir aktif lalu release.</p>

                <div class="mt-4 flex gap-2">
                    <input
                        class="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100 dark:border-emerald-400/20 dark:bg-white/[0.06] dark:text-slate-100 dark:focus:ring-emerald-500/20"
                        type="search"
                        placeholder="Search user (id / username / static ip)"
                        x-model="manualIsolir.release.search"
                        @keyup.enter.prevent="searchManualUsers('release')"
                    >
                    <button
                        type="button"
                        class="rounded-xl border border-emerald-200 bg-white px-3 py-2 text-sm font-bold text-emerald-700 disabled:opacity-50 dark:border-emerald-400/30 dark:bg-transparent dark:text-emerald-200"
                        :disabled="!manualIsolir.router_id || manualIsolir.release.loading"
                        @click="searchManualUsers('release')"
                    >
                        Cari
                    </button>
                </div>

                <label class="mt-3 block">
                    <span class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-200">User</span>
                    <select
                        class="mt-2 w-full rounded-xl border border-emerald-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100 dark:border-emerald-400/20 dark:bg-white/[0.06] dark:text-slate-100 dark:focus:ring-emerald-500/20"
                        x-model="manualIsolir.release.user_id"
                    >
                        <option value="">Pilih user...</option>
                        <template x-for="item in manualIsolir.release.options" :key="item.service_id">
                            <option :value="item.service_id" x-text="item.label"></option>
                        </template>
                    </select>
                </label>

                <button
                    type="button"
                    class="mt-4 w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-black text-white shadow-lg shadow-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canRunManualOperation('release')"
                    @click="runManualRelease"
                >
                    Release User
                </button>
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.03]">
            <h3 class="text-sm font-black uppercase tracking-[0.18em] text-slate-700 dark:text-slate-300">Operation Result</h3>

            <div x-show="manualIsolir.result" x-cloak class="mt-3 rounded-2xl border px-4 py-3" :class="manualIsolir.result?.success ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200' : 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200'">
                <p class="text-sm font-black" x-text="manualIsolir.result?.success ? 'success' : 'error'"></p>
                <p class="mt-1 text-sm font-semibold" x-text="manualIsolir.result?.message ?? '-'"></p>
                <p class="mt-1 text-xs" x-text="manualIsolir.result?.at ? `Updated: ${formatValue(manualIsolir.result.at)}` : ''"></p>
                <pre class="mt-3 overflow-auto rounded-xl bg-white/70 p-3 text-xs text-slate-700 dark:bg-[#07111f]/70 dark:text-slate-200" x-text="JSON.stringify(manualIsolir.result?.data ?? {}, null, 2)"></pre>
            </div>

            <p x-show="!manualIsolir.result" class="mt-3 text-sm text-slate-500 dark:text-slate-400">Belum ada operasi dijalankan.</p>
        </section>
    </section>

    <section x-show="page === 'router-sync' && !isPlaceholderCurrent()" x-cloak class="space-y-5 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
        <div class="grid gap-4 lg:grid-cols-[360px_1fr]">
            <label class="block">
                <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Pilih Router Aktif</span>
                <select
                    class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                    x-model="routerSync.router_id"
                    @change="onRouterSyncRouterChanged()"
                >
                    <option value="">Pilih router...</option>
                    <template x-for="router in activeRouterSyncRouters()" :key="router.id">
                        <option :value="router.id" x-text="routerSyncRouterLabel(router)"></option>
                    </template>
                </select>
            </label>

            <div x-show="!routerSync.router_id" class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                Router belum dipilih. Pilih router aktif terlebih dahulu untuk menjalankan sinkronisasi.
            </div>
        </div>

        {{-- Router Stats Section --}}
        <div x-show="routerSync.router_id" class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-[#07111f]/70">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-black uppercase tracking-[0.18em] text-slate-700 dark:text-slate-300">Statistik Router</h3>
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-200"
                    :disabled="routerSync.loadingStats"
                    @click="loadRouterStats()"
                >
                    <span x-show="routerSync.loadingStats">Loading...</span>
                    <span x-show="!routerSync.loadingStats">Refresh</span>
                </button>
            </div>

            <div x-show="routerSync.loadingStats" class="text-sm text-slate-500 dark:text-slate-400">Memuat statistik router...</div>

            {{-- PPPoE Stats --}}
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-4">
                <article class="rounded-2xl border border-blue-200 bg-blue-50/70 p-3 dark:border-blue-400/20 dark:bg-blue-500/10">
                    <p class="text-[10px] font-black uppercase tracking-wide text-blue-700 dark:text-blue-300">PPPoE Secrets</p>
                    <p class="mt-1 text-2xl font-black text-blue-900 dark:text-blue-100" x-text="routerSync.stats?.pppoe?.active_secrets ?? '-'"></p>
                    <p class="text-[10px] text-blue-600 dark:text-blue-400">dari <span x-text="routerSync.stats?.pppoe?.total_secrets ?? '-'" ></span> total</p>
                </article>
                <article class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-400/20 dark:bg-emerald-500/10">
                    <p class="text-[10px] font-black uppercase tracking-wide text-emerald-700 dark:text-emerald-300">PPPoE Active</p>
                    <p class="mt-1 text-2xl font-black text-emerald-900 dark:text-emerald-100" x-text="routerSync.stats?.pppoe?.active_users ?? '-'"></p>
                    <p class="text-[10px] text-emerald-600 dark:text-emerald-400">user aktif</p>
                </article>
                <article class="rounded-2xl border border-indigo-200 bg-indigo-50/70 p-3 dark:border-indigo-400/20 dark:bg-indigo-500/10">
                    <p class="text-[10px] font-black uppercase tracking-wide text-indigo-700 dark:text-indigo-300">DHCP Leases</p>
                    <p class="mt-1 text-2xl font-black text-indigo-900 dark:text-indigo-100" x-text="routerSync.stats?.dhcp?.bound_leases ?? '-'"></p>
                    <p class="text-[10px] text-indigo-600 dark:text-indigo-400">bound / <span x-text="routerSync.stats?.dhcp?.total_leases ?? '-'" ></span></p>
                </article>
                <article class="rounded-2xl border border-violet-200 bg-violet-50/70 p-3 dark:border-violet-400/20 dark:bg-violet-500/10">
                    <p class="text-[10px] font-black uppercase tracking-wide text-violet-700 dark:text-violet-300">IP Pool Addr</p>
                    <p class="mt-1 text-2xl font-black text-violet-900 dark:text-violet-100" x-text="routerSync.stats?.ip_pool?.total_addresses ?? '-'"></p>
                    <p class="text-[10px] text-violet-600 dark:text-violet-400"><span x-text="routerSync.stats?.ip_pool?.total_pools ?? '-'" ></span> pools</p>
                </article>
            </div>

            {{-- Interface Stats --}}
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white/70 p-3 dark:border-white/10 dark:bg-white/[0.05]">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-600 dark:text-slate-400">Interfaces</p>
                    <p class="mt-1 text-2xl font-black text-slate-900 dark:text-slate-100">
                        <span x-text="routerSync.stats?.interfaces?.active ?? '-'"></span>
                        <span class="text-sm font-normal">/ <span x-text="routerSync.stats?.interfaces?.total ?? '-'" ></span></span>
                    </p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400">active interfaces</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white/70 p-3 dark:border-white/10 dark:bg-white/[0.05]">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-600 dark:text-slate-400">VLAN Interfaces</p>
                    <p class="mt-1 text-2xl font-black text-slate-900 dark:text-slate-100" x-text="routerSync.stats?.interfaces?.vlan_count ?? '-'"></p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400">VLAN aktif</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white/70 p-3 dark:border-white/10 dark:bg-white/[0.05]">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-600 dark:text-slate-400">IP Addresses</p>
                    <p class="mt-1 text-2xl font-black text-slate-900 dark:text-slate-100">
                        <span x-text="routerSync.stats?.addresses?.active ?? '-'"></span>
                        <span class="text-sm font-normal">/ <span x-text="routerSync.stats?.addresses?.total ?? '-'" ></span></span>
                    </p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400">static addresses</p>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white/70 p-3 dark:border-white/10 dark:bg-white/[0.05]">
                    <p class="text-[10px] font-black uppercase tracking-wide text-slate-600 dark:text-slate-400">Status</p>
                    <p class="mt-1">
                        <span x-show="routerSync.stats?.success !== false" class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-black text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">Connected</span>
                        <span x-show="routerSync.stats?.success === false" class="inline-flex rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-black text-rose-700 dark:bg-rose-500/20 dark:text-rose-300">Error</span>
                    </p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400" x-text="routerSync.stats?.message ?? ''"></p>
                </article>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <button
                type="button"
                class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-bold text-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canRunRouterSyncOperation()"
                @click="runRouterSync('pppoe')"
            >
                Sync PPPoE
            </button>
            <button
                type="button"
                class="rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-bold text-indigo-800 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canRunRouterSyncOperation()"
                @click="runRouterSync('static')"
            >
                Sync Static
            </button>
            <button
                type="button"
                class="rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-bold text-cyan-800 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canRunRouterSyncOperation()"
                @click="runRouterSync('address-list')"
            >
                Sync Address List
            </button>
            <button
                type="button"
                class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/20 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canRunRouterSyncOperation()"
                @click="runRouterSync('all')"
            >
                Sync All
            </button>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.03]">
            <h3 class="text-sm font-black uppercase tracking-[0.18em] text-slate-700 dark:text-slate-300">Operation Result</h3>

            <div x-show="routerSync.result" x-cloak class="mt-3 rounded-2xl border px-4 py-3" :class="routerSync.result?.success ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200' : 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200'">
                <p class="text-sm font-black" x-text="routerSync.result?.success ? 'success' : 'error'"></p>
                <p class="mt-1 text-sm font-semibold" x-text="routerSync.result?.operation ?? '-'"></p>
                <p class="mt-1 text-sm font-semibold" x-text="routerSync.result?.message ?? '-'"></p>
                <p class="mt-1 text-xs" x-text="routerSync.result?.at ? `Updated: ${formatValue(routerSync.result.at)}` : ''"></p>
                <pre class="mt-3 overflow-auto rounded-xl bg-white/70 p-3 text-xs text-slate-700 dark:bg-[#07111f]/70 dark:text-slate-200" x-text="JSON.stringify(routerSync.result?.data ?? {}, null, 2)"></pre>
            </div>

            <p x-show="!routerSync.result" class="mt-3 text-sm text-slate-500 dark:text-slate-400">Belum ada sinkronisasi dijalankan.</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/[0.02]">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-black uppercase tracking-[0.18em] text-slate-700 dark:text-slate-300">Recent Router Sync Logs</h3>
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-200"
                    :disabled="routerSync.loadingLogs"
                    @click="loadRouterSyncLogs"
                >
                    Refresh
                </button>
            </div>

            <div x-show="routerSync.loadingLogs" class="mt-3 text-xs text-slate-500 dark:text-slate-400">Memuat riwayat...</div>

            <div x-show="!routerSync.loadingLogs && routerSync.recentLogs.length === 0" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                Belum ada riwayat router sync.
            </div>

            <div x-show="!routerSync.loadingLogs && routerSync.recentLogs.length > 0" class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-[0.16em] text-slate-500 dark:bg-white/[0.03] dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2">Time</th>
                            <th class="px-3 py-2">Action</th>
                            <th class="px-3 py-2">User</th>
                            <th class="px-3 py-2">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                        <template x-for="log in routerSync.recentLogs" :key="log.id">
                            <tr>
                                <td class="px-3 py-2 text-xs text-slate-600 dark:text-slate-300" x-text="formatValue(log.created_at, { type: 'datetime' })"></td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex rounded-full bg-blue-100 px-2 py-1 text-[11px] font-bold text-blue-700 dark:bg-blue-500/20 dark:text-blue-200" x-text="log.action"></span>
                                </td>
                                <td class="px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200" x-text="log.user?.username ?? '-'"></td>
                                <td class="px-3 py-2 text-xs text-slate-600 dark:text-slate-300" x-text="log.description ?? '-'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    {{-- PPPoE Import Section --}}
    <section x-show="page === 'pppoe-import' && !isPlaceholderCurrent()" x-cloak class="space-y-5 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">
                        CCR-Warnet
                    </span>
                    <span x-show="pppoeImport.router" class="text-xs text-slate-500 dark:text-slate-400" x-text="pppoeImport.router?.name ?? ''"></span>
                </div>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    <span x-text="pppoeImport.candidates.length"></span> PPPoE secret belum diimport.
                    Pilih username yang akan diimport sebagai customer baru.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-200"
                    :disabled="pppoeImport.loading"
                    @click="loadPppoeImportCandidates"
                >
                    <span x-show="pppoeImport.loading">Memuat...</span>
                    <span x-show="!pppoeImport.loading">Muat Ulang</span>
                </button>
                <button
                    type="button"
                    class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-600/20 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="pppoeImport.selected.size === 0 || pppoeImport.importing"
                    @click="executePppoeImport"
                >
                    <span x-show="pppoeImport.importing">Mengimport...</span>
                    <span x-show="!pppoeImport.importing">
                        Import <span x-text="pppoeImport.selected.size"></span> Customer Terpilih
                    </span>
                </button>
            </div>
        </div>

        {{-- Prefix Filter --}}
        <div x-show="pppoeImport.candidates.length > 0" class="flex items-center gap-3">
            <input
                type="text"
                class="w-full max-w-xs rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                placeholder="Filter prefix (contoh: ModeM-)"
                x-model.debounce.300ms="pppoeImport.prefixFilter"
            >
            <button
                type="button"
                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 dark:border-white/10 dark:text-slate-200"
                @click="pppoeImport.prefixFilter = ''"
            >
                Reset
            </button>
        </div>

        {{-- Filter Info --}}
        <div x-show="pppoeImport.prefixFilter" class="rounded-2xl border border-blue-200 bg-blue-50/70 px-4 py-2 text-sm text-blue-700 dark:border-blue-400/20 dark:bg-blue-500/10 dark:text-blue-200">
            Menampilkan <span class="font-bold" x-text="pppoeFilteredCount"></span> dari <span x-text="pppoeImport.candidates.length"></span> secret (filter: "<span x-text="pppoeImport.prefixFilter"></span>")
        </div>

        {{-- Selection Controls --}}
        <div x-show="pppoeImport.candidates.length > 0" class="flex items-center gap-3">
            <button
                type="button"
                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 dark:border-white/10 dark:text-slate-200"
                @click="selectAllPppoeCandidates(true)"
            >
                ✓ Pilih Semua yang Tampil (<span x-text="pppoeFilteredCount"></span>)
            </button>
            <button
                type="button"
                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 dark:border-white/10 dark:text-slate-200"
                @click="selectAllPppoeCandidates(false)"
            >
                ✗ Batal Semua
            </button>
            <span class="text-xs text-slate-500 dark:text-slate-400">
                <span x-text="pppoeImport.selected.size"></span> / <span x-text="pppoeFilteredCount"></span> dipilih
            </span>
        </div>

        {{-- Loading State --}}
        <div x-show="pppoeImport.loading" class="flex items-center justify-center py-12">
            <div class="h-8 w-8 animate-spin rounded-full border-4 border-slate-200 border-t-blue-600"></div>
            <span class="ml-3 text-slate-500">Memuat data dari Mikrotik...</span>
        </div>

        {{-- Empty State --}}
        <div x-show="!pppoeImport.loading && pppoeImport.candidates.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/50 p-8 text-center dark:border-white/10 dark:bg-white/[0.02]">
            <p class="text-lg font-black text-slate-700 dark:text-slate-200">Semua PPPoE secret sudah diimport</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Tidak ada username baru yang bisa diimport dari Mikrotik.</p>
        </div>

        {{-- Candidates Table --}}
        <div x-show="!pppoeImport.loading && pppoeImport.candidates.length > 0" class="overflow-hidden rounded-3xl border border-slate-200 dark:border-white/10">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-[0.16em] text-slate-500 dark:bg-white/[0.03] dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 w-10"></th>
                            <th class="px-4 py-3">Username PPPoE</th>
                            <th class="px-4 py-3">Profile</th>
                            <th class="px-4 py-3">Comment (dari Mikrotik)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-white/10 dark:bg-transparent">
                        <template x-for="candidate in getFilteredPppoeCandidates()" :key="candidate.username">
                            <tr class="transition hover:bg-blue-50/50 dark:hover:bg-white/[0.04]">
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        class="h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                        :checked="pppoeImport.selected.has(candidate.username)"
                                        @change="togglePppoeCandidate(candidate.username, $event.target.checked)"
                                    >
                                </td>
                                <td class="px-4 py-3 font-mono font-bold text-slate-900 dark:text-white" x-text="candidate.username"></td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300" x-text="candidate.profile || '-'"></td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-sm" x-text="candidate.comment || '-'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Info Note --}}
        <div class="rounded-2xl border border-blue-200 bg-blue-50/70 p-4 dark:border-blue-400/20 dark:bg-blue-500/10">
            <p class="text-sm font-bold text-blue-800 dark:text-blue-200">Informasi Import</p>
            <ul class="mt-2 space-y-1 text-xs text-blue-700 dark:text-blue-300">
                <li>- Customer baru akan menggunakan <strong>nama = username PPPoE</strong> (bisa diedit setelah import)</li>
                <li>- Default: status=active, ip_count=1, monthly_price=0, billing_day=1</li>
                <li>- Password PPPoE diambil dari Mikrotik dan disimpan apa adanya</li>
                <li>- Data harga dan layanan harus diisi manual setelah import</li>
            </ul>
        </div>
    </section>

    <section x-show="page === 'monitoring' && !isPlaceholderCurrent()" x-cloak class="grid gap-3 sm:grid-cols-3 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
        <article class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5 dark:border-white/10 dark:bg-[#07111f]/70">
            <div class="flex items-center justify-between">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Total Monitor</p>
                <span class="rounded-full bg-blue-100 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">MikroTik</span>
            </div>
            <p class="mt-3 text-3xl font-black tracking-tight text-slate-950 dark:text-white" x-text="pageMeta.summary?.total ?? pagination.total ?? 0"></p>
        </article>
        <article class="rounded-3xl border border-emerald-200 bg-emerald-50/80 p-5 dark:border-emerald-500/30 dark:bg-emerald-500/10">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">PPPoE Online</p>
            <p class="mt-3 text-3xl font-black tracking-tight text-emerald-900 dark:text-emerald-100" x-text="pageMeta.summary?.online_count ?? '—'"></p>
        </article>
        <article class="rounded-3xl border border-rose-200 bg-rose-50/80 p-5 dark:border-rose-500/30 dark:bg-rose-500/10">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-rose-700 dark:text-rose-300">PPPoE Offline</p>
            <p class="mt-3 text-3xl font-black tracking-tight text-rose-900 dark:text-rose-100" x-text="pageMeta.summary?.offline_count ?? '—'"></p>
        </article>
    </section>

    <section x-show="(page === 'ont-online' || page === 'ont-offline') && !isPlaceholderCurrent()" x-cloak class="flex flex-wrap items-center gap-3 rounded-[2rem] border border-slate-200 bg-white px-5 py-4 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
        <span class="inline-flex items-center gap-2 rounded-full bg-indigo-100 px-3 py-1.5 text-xs font-black uppercase tracking-wide text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">
            <span class="h-2 w-2 rounded-full bg-indigo-500"></span>
            Sumber: GenieACS
        </span>
        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300">
            Threshold online: last_seen_at &lt; 10 menit
        </span>
        <span x-show="page === 'ont-online'" class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
            <span x-text="pagination.total ?? 0"></span> ONT online
        </span>
        <span x-show="page === 'ont-offline'" class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            <span x-text="pagination.total ?? 0"></span> ONT offline / belum terdeteksi
        </span>
    </section>

    {{-- IP Pools Section --}}
    <section x-show="page === 'ip-pools' && !isPlaceholderCurrent()" x-cloak class="space-y-5 rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
        {{-- Router Selection --}}
        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-[#07111f]/70">
            <div class="grid gap-4 lg:grid-cols-[1fr_auto]">
                <label class="block">
                    <span class="text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Pilih Router (Wajib)</span>
                    <select
                        class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                        x-model="ipPools.router_id"
                        @change="loadIpPoolsPage()"
                    >
                        <option value="">Pilih router aktif...</option>
                        <template x-for="router in activeIpPoolsRouters()" :key="router.id">
                            <option :value="router.id" x-text="routerIpPoolsLabel(router)"></option>
                        </template>
                    </select>
                </label>

                <div class="flex items-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-600/20 disabled:opacity-50"
                        :disabled="!ipPools.router_id || ipPools.loading"
                        @click="refreshIpPools()"
                    >
                        Refresh
                    </button>
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm disabled:opacity-50 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                        :disabled="!ipPools.router_id || ipPools.loadingPreview"
                        @click="previewIpPools()"
                    >
                        <span x-show="!ipPools.loadingPreview">Pilih Pool dari Mikrotik</span>
                        <span x-show="ipPools.loadingPreview">Memuat...</span>
                    </button>
                </div>
            </div>
        </div>

        <div x-show="!ipPools.router_id" class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
            Pilih router terlebih dahulu untuk melihat IP Pools.
        </div>

        {{-- Summary Cards --}}
        <div x-show="ipPools.router_id && ipPools.summary" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Pools</p>
                <p class="mt-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white" x-text="ipPools.summary?.total_pools ?? 0"></p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Total IP</p>
                <p class="mt-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white" x-text="ipPools.summary?.total_ips ?? 0"></p>
            </article>
            <article class="rounded-3xl border border-emerald-200 bg-emerald-50/80 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-700 dark:text-emerald-300">IP Free</p>
                <p class="mt-2 text-2xl font-black tracking-tight text-emerald-900 dark:text-emerald-100" x-text="ipPools.summary?.total_free_ips ?? 0"></p>
            </article>
            <article class="rounded-3xl border border-rose-200 bg-rose-50/80 p-4 dark:border-rose-500/30 dark:bg-rose-500/10">
                <p class="text-[10px] font-black uppercase tracking-wider text-rose-700 dark:text-rose-300">IP Used</p>
                <p class="mt-2 text-2xl font-black tracking-tight text-rose-900 dark:text-rose-100" x-text="ipPools.summary?.total_used_ips ?? 0"></p>
            </article>
            <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Usage</p>
                <p class="mt-2 text-2xl font-black tracking-tight" :class="usageTextClass(ipPools.summary?.overall_usage_percentage ?? 0)" x-text="(ipPools.summary?.overall_usage_percentage ?? 0) + '%'"></p>
            </article>
        </div>

        {{-- Filters --}}
        <div x-show="ipPools.router_id" class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-[#07111f]/70">
            <div class="grid gap-3 lg:grid-cols-3">
                <label class="block">
                    <span class="text-[10px] font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Filter VLAN ID</span>
                    <input
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                        type="number"
                        min="1"
                        max="4094"
                        placeholder="Semua VID"
                        x-model="ipPools.filters.vlan_id"
                        @change="refreshIpPools()"
                    >
                </label>
                <label class="block">
                    <span class="text-[10px] font-black uppercase tracking-wide text-slate-500 dark:text-slate-400">Min Free IPs</span>
                    <input
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:focus:ring-blue-500/10"
                        type="number"
                        min="1"
                        placeholder="1"
                        x-model.number="ipPools.filters.min_free_ips"
                        @change="refreshIpPools()"
                    >
                </label>
                <label class="flex items-center gap-2 pt-5">
                    <input
                        type="checkbox"
                        class="h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        x-model="ipPools.filters.available_only"
                        @change="refreshIpPools()"
                    >
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Pool dengan IP tersedia</span>
                </label>
            </div>
        </div>

        {{-- IP Pools Table --}}
        <div x-show="ipPools.router_id && ipPools.pools.length > 0" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
            <div class="border-b border-slate-100 px-5 py-3 text-sm font-black text-slate-900 dark:border-white/10 dark:text-white">
                Daftar IP Pools
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.16em] text-slate-500 dark:bg-white/[0.03] dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Pool Name</th>
                            <th class="px-4 py-3">VLAN ID</th>
                            <th class="px-4 py-3">Interface</th>
                            <th class="px-4 py-3">DHCP Server</th>
                            <th class="px-4 py-3">Ranges</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Used</th>
                            <th class="px-4 py-3 text-right">Free</th>
                            <th class="px-4 py-3 text-right">Usage %</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                        <template x-for="pool in ipPools.pools" :key="pool.name">
                            <tr class="transition hover:bg-blue-50/50 dark:hover:bg-white/[0.03]">
                                <td class="px-4 py-3 font-bold text-slate-900 dark:text-white" x-text="pool.name"></td>
                                <td class="px-4 py-3">
                                    <span x-show="pool.vlan_id" class="inline-flex rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-bold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300" x-text="pool.vlan_id"></span>
                                    <span x-show="!pool.vlan_id" class="text-slate-400">-</span>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300" x-text="pool.interface ?? '-'"></td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300" x-text="pool.dhcp_server_name ?? '-'"></td>
                                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400" x-text="pool.all_ranges_string ?? pool.primary_range ?? '-'"></td>
                                <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-200" x-text="pool.total_ips"></td>
                                <td class="px-4 py-3 text-right font-mono text-rose-600 dark:text-rose-300" x-text="pool.used_ips"></td>
                                <td class="px-4 py-3 text-right font-mono text-emerald-600 dark:text-emerald-300" x-text="pool.free_ips"></td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="w-16 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
                                            <div class="h-full rounded-full" :class="usageColorClass(pool.usage_percentage)" :style="`width: ${pool.usage_percentage}%`"></div>
                                        </div>
                                        <span class="w-10 text-right text-xs font-bold" :class="usageTextClass(pool.usage_percentage)" x-text="pool.usage_percentage + '%'"></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span x-show="pool.availability_status === 'available'" class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">Available</span>
                                    <span x-show="pool.availability_status === 'reserved'" class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">Sudah dipakai</span>
                                    <span x-show="pool.availability_status === 'full'" class="inline-flex rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-rose-700 dark:bg-rose-500/20 dark:text-rose-300">Full</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="ipPools.router_id && ipPools.pools.length === 0 && !ipPools.loading" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/50 p-8 text-center dark:border-white/10 dark:bg-white/[0.02]">
            <p class="text-lg font-black text-slate-700 dark:text-slate-200">Tidak ada IP Pool</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Pool tidak ditemukan di router ini atau filter terlalu ketat.</p>
        </div>

        {{-- Pool Selection Panel --}}
        <div x-show="ipPools.showSelection" x-cloak class="rounded-2xl border border-blue-200 bg-blue-50/70 p-4 dark:border-blue-500/30 dark:bg-blue-500/10">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-black text-blue-900 dark:text-blue-100">Pilih Pool yang Mau Di-track</h4>
                    <p class="mt-0.5 text-xs text-blue-700 dark:text-blue-300">
                        <span x-text="ipPools.selectedPools.length"></span> pool dipilih dari
                        <span x-text="ipPools.preview.length"></span> pool di Mikrotik
                    </p>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="ipPools.selectedPools = ipPools.preview.map(p => p.pool_name)"
                        class="rounded-lg border border-blue-300 bg-white px-3 py-1.5 text-xs font-bold text-blue-700 dark:border-blue-500/40 dark:bg-blue-500/10 dark:text-blue-200"
                    >Pilih Semua</button>
                    <button
                        type="button"
                        @click="ipPools.selectedPools = []"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                    >Batal Semua</button>
                    <button
                        type="button"
                        @click="ipPools.showSelection = false"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300"
                    >Tutup</button>
                </div>
            </div>

            <div class="mb-3 flex gap-2">
                <button
                    type="button"
                    @click="ipPools.selectedPools = ipPools.preview.filter(p => p.vlan_id >= 1001 && p.vlan_id <= 1255).map(p => p.pool_name)"
                    class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white"
                >Pilih VLAN 1001-1255</button>
                <button
                    type="button"
                    @click="ipPools.selectedPools = ipPools.preview.filter(p => p.availability_status === 'available').map(p => p.pool_name)"
                    class="rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-bold text-emerald-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200"
                >Pilih Semua Available</button>
            </div>

            <div class="max-h-80 overflow-y-auto rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-white/[0.04]">
                <table class="w-full text-xs">
                    <thead class="sticky top-0 bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-black uppercase tracking-wide text-slate-500">Pilih</th>
                            <th class="px-3 py-2 text-left font-black uppercase tracking-wide text-slate-500">Pool Name</th>
                            <th class="px-3 py-2 text-left font-black uppercase tracking-wide text-slate-500">VLAN</th>
                            <th class="px-3 py-2 text-left font-black uppercase tracking-wide text-slate-500">Interface</th>
                            <th class="px-3 py-2 text-right font-black uppercase tracking-wide text-slate-500">Used/Total</th>
                            <th class="px-3 py-2 text-left font-black uppercase tracking-wide text-slate-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                        <template x-for="pool in ipPools.preview" :key="pool.pool_name">
                            <tr
                                class="cursor-pointer transition hover:bg-blue-50 dark:hover:bg-blue-500/10"
                                :class="isPoolSelected(pool.pool_name) ? 'bg-blue-50/80 dark:bg-blue-500/10' : ''"
                                @click="togglePoolSelection(pool.pool_name)"
                            >
                                <td class="px-3 py-2">
                                    <input
                                        type="checkbox"
                                        :checked="isPoolSelected(pool.pool_name)"
                                        @click.stop="togglePoolSelection(pool.pool_name)"
                                        class="h-4 w-4 rounded border-slate-300 text-blue-600"
                                    >
                                </td>
                                <td class="px-3 py-2 font-mono font-bold text-slate-800 dark:text-white" x-text="pool.pool_name"></td>
                                <td class="px-3 py-2">
                                    <span x-show="pool.vlan_id" class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300" x-text="pool.vlan_id"></span>
                                    <span x-show="!pool.vlan_id" class="text-slate-400">-</span>
                                </td>
                                <td class="px-3 py-2 text-slate-500 dark:text-slate-400" x-text="pool.interface ?? '-'"></td>
                                <td class="px-3 py-2 text-right font-mono text-slate-600 dark:text-slate-300">
                                    <span x-text="pool.used_ips"></span>/<span x-text="pool.total_ips"></span>
                                </td>
                                <td class="px-3 py-2">
                                    <span x-show="pool.availability_status === 'available'" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-700">Available</span>
                                    <span x-show="pool.availability_status === 'reserved'" class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase text-amber-700">Sudah dipakai</span>
                                    <span x-show="pool.availability_status === 'full'" class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black uppercase text-rose-700">Full</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="mt-3 flex justify-end">
                <button
                    type="button"
                    @click="savePoolSelection()"
                    :disabled="ipPools.selectedPools.length === 0"
                    class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-bold text-white shadow-lg shadow-blue-600/20 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Simpan Pilihan (<span x-text="ipPools.selectedPools.length"></span> pool)
                </button>
            </div>
        </div>

        {{-- VID Availability Section --}}
        <div x-show="ipPools.router_id && ipPools.vidsWithAvailability.length > 0" class="space-y-3">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">VID dengan Ketersediaan IP Pool</h3>
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10">
                        <thead class="bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.16em] text-slate-500 dark:bg-white/[0.03] dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">VID</th>
                                <th class="px-4 py-3">VLAN Name</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Pool Count</th>
                                <th class="px-4 py-3 text-right">Total IPs</th>
                                <th class="px-4 py-3 text-right">Free IPs</th>
                                <th class="px-4 py-3 text-right">Usage</th>
                                <th class="px-4 py-3">Available</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                            <template x-for="vid in ipPools.vidsWithAvailability" :key="vid.vid_id ?? vid.vid">
                                <tr class="transition hover:bg-blue-50/50 dark:hover:bg-white/[0.03]">
                                    <td class="px-4 py-3 font-bold text-slate-900 dark:text-white" x-text="vid.vid"></td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300" x-text="vid.vlan_name ?? '-'"></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide" :class="statusClass(vid.status)" x-text="formatValue(vid.status, { type: 'status' })"></span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-200" x-text="vid.pool_utilization?.pool_count ?? 0"></td>
                                    <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-200" x-text="vid.pool_utilization?.total_ips ?? 0"></td>
                                    <td class="px-4 py-3 text-right font-mono text-emerald-600 dark:text-emerald-300" x-text="vid.pool_utilization?.free_ips ?? 0"></td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="text-xs font-bold" :class="usageTextClass(vid.pool_utilization?.usage_percentage ?? 0)" x-text="(vid.pool_utilization?.usage_percentage ?? 0) + '%'"></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span x-show="vid.is_available" class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">Yes</span>
                                        <span x-show="!vid.is_available" class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-slate-600 dark:bg-white/10 dark:text-slate-400">No</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Suggested VIDs for Assignment --}}
        <div x-show="ipPools.router_id && ipPools.utilization?.suggested_vids_for_assignment?.length > 0" class="space-y-3">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">VID yang Disarankan untuk Assignment Customer Baru</h3>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="suggestion in (ipPools.utilization?.suggested_vids_for_assignment ?? [])" :key="suggestion.vlan_id">
                    <article class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
                        <div class="flex items-center justify-between">
                            <span class="text-lg font-black text-emerald-800 dark:text-emerald-100">VID <span x-text="suggestion.vlan_id"></span></span>
                            <span class="rounded-full bg-emerald-200 px-2 py-0.5 text-[10px] font-black text-emerald-800 dark:bg-emerald-500/30 dark:text-emerald-200" x-text="suggestion.pool_count + ' pools'"></span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-sm">
                            <span class="text-emerald-700 dark:text-emerald-300">Free IPs:</span>
                            <span class="font-bold text-emerald-800 dark:text-emerald-100" x-text="suggestion.free_ips"></span>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-xs">
                            <span class="text-emerald-600 dark:text-emerald-400">Usage:</span>
                            <span class="font-bold text-emerald-700 dark:text-emerald-200" x-text="suggestion.usage_percentage + '%'"></span>
                        </div>
                        <div class="mt-2 w-full overflow-hidden rounded-full bg-emerald-200 dark:bg-emerald-500/30">
                            <div class="h-1.5 rounded-full bg-emerald-500" :style="`width: ${100 - suggestion.usage_percentage}%`"></div>
                        </div>
                    </article>
                </template>
            </div>
        </div>
    </section>

    <section x-show="isPlaceholderCurrent()" x-cloak class="overflow-hidden rounded-[2rem] border border-dashed border-blue-300 bg-white shadow-sm shadow-slate-950/5 dark:border-blue-400/30 dark:bg-white/[0.04]">
        <div class="grid gap-0 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="p-6 sm:p-8">
                <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-black uppercase tracking-wide text-blue-700">
                    Coming soon / Backend endpoint pending
                </span>
                <h3 class="mt-5 text-3xl font-black tracking-tight text-slate-950 dark:text-white" x-text="currentTitle()"></h3>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-400" x-text="currentDescription()"></p>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <button
                        type="button"
                        class="rounded-2xl bg-slate-200 px-5 py-3 text-sm font-black text-slate-500"
                        disabled
                        x-text="currentConfig().actionLabel"
                    ></button>
                    <button
                        type="button"
                        class="rounded-2xl border border-blue-200 bg-white px-5 py-3 text-sm font-black text-blue-700 transition hover:bg-blue-50"
                        @click="refreshCurrentPage"
                    >
                        Refresh shell
                    </button>
                </div>
            </div>

            <div class="border-t border-blue-950/10 bg-blue-50/70 p-6 dark:border-white/10 dark:bg-[#07111f]/70 sm:p-8 lg:border-l lg:border-t-0">
                <p class="text-xs font-black uppercase tracking-[0.24em] text-blue-700">Kebutuhan backend/API</p>
                <ul class="mt-5 space-y-3">
                    <template x-for="need in (currentConfig().backendNeeds ?? [])" :key="need">
                        <li class="flex gap-3 rounded-2xl bg-white/80 p-3 text-sm text-slate-700 shadow-sm dark:bg-white/[0.04] dark:text-slate-300">
                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-blue-500"></span>
                            <span x-text="need"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </section>

    <div x-show="loading && !isPlaceholderCurrent() && page !== 'technician-dashboard' && page !== 'router-sync' && page !== 'fiber-network-map' && page !== 'ip-pools'" class="grid gap-3">
        <template x-for="i in 5" :key="i">
            <div class="h-16 animate-pulse rounded-3xl bg-white/70 dark:bg-white/[0.05]"></div>
        </template>
    </div>

    <x-table x-show="!loading && !isPlaceholderCurrent() && page !== 'technician-dashboard' && page !== 'router-sync' && page !== 'fiber-network-map' && page !== 'ip-pools'">
        <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-[0.18em] text-slate-500 dark:bg-white/[0.03] dark:text-slate-400">
            <tr>
                <template x-for="column in currentColumns()" :key="column.key">
                    <th class="px-5 py-4" x-text="column.label"></th>
                </template>
                <th class="px-5 py-4 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white/70 text-sm dark:divide-white/10 dark:bg-transparent">
            <template x-for="row in items" :key="row.id">
                <tr class="transition hover:bg-blue-50/70 dark:hover:bg-white/[0.04]">
                    <template x-for="column in currentColumns()" :key="column.key">
                        <td class="px-5 py-4 align-top">
                            <template x-if="column.type === 'status'">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide" :class="statusClass(valueFor(row, column))" x-text="formatValue(valueFor(row, column), column)"></span>
                            </template>
                            <template x-if="column.type !== 'status'">
                                <span class="font-medium text-slate-700 dark:text-slate-300" x-text="formatValue(valueFor(row, column), column)"></span>
                            </template>
                        </td>
                    </template>
                    <td class="px-5 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <button class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 dark:border-white/10 dark:text-slate-300" @click="openDetail(row)">Detail</button>
                            <button class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 dark:border-white/10 dark:text-slate-300" x-show="canEditCurrent() && canEditRow(row)" @click="openEdit(row)">Edit</button>
                            <template x-for="action in rowActions(row)" :key="action.key">
                                <button class="rounded-xl px-3 py-2 text-xs font-bold" :class="action.class" @click="runRowAction(action, row)" x-text="action.label"></button>
                            </template>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </x-table>

    <div x-show="!loading && !isPlaceholderCurrent() && page !== 'technician-dashboard' && page !== 'router-sync' && page !== 'fiber-network-map' && page !== 'ip-pools' && items.length === 0" class="rounded-[2rem] border border-dashed border-slate-300 bg-white/70 p-10 text-center dark:border-white/10 dark:bg-white/[0.04]">
        <p class="text-2xl font-black text-slate-900 dark:text-white">Belum ada data.</p>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Coba ubah pencarian, refresh, atau buat data baru jika modul ini mendukung create.</p>
    </div>

    <div x-show="!isPlaceholderCurrent() && page !== 'technician-dashboard' && page !== 'router-sync' && page !== 'fiber-network-map' && page !== 'ip-pools'">
        <x-pagination />
    </div>
</div>
