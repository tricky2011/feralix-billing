<div class="space-y-5">
    <section class="rounded-[2rem] border border-emerald-950/10 bg-white/80 p-5 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.28em] text-emerald-700" x-text="currentSectionLabel()"></p>
                <h2 class="mt-2 font-display text-4xl" x-text="currentTitle()"></h2>
                <p class="mt-2 text-sm text-slate-600" x-text="currentDescription()"></p>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <input
                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-emerald-700"
                    type="search"
                    placeholder="Cari data..."
                    x-model.debounce.500ms="filters.search"
                    @input="changePage(1)"
                >
                <button
                    type="button"
                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold"
                    @click="loadPage"
                >
                    Refresh
                </button>
                <button
                    type="button"
                    class="rounded-2xl bg-emerald-950 px-4 py-3 text-sm font-bold text-white disabled:hidden"
                    @click="openCreate"
                    :disabled="!canCreateCurrent()"
                >
                    Tambah
                </button>
            </div>
        </div>

        <div x-show="hasTabs()" class="mt-5 flex flex-wrap gap-2">
            <template x-for="tab in currentTabs()" :key="tab.key">
                <button
                    type="button"
                    class="rounded-2xl px-4 py-2 text-sm font-bold transition"
                    :class="activeTab === tab.key ? 'bg-amber-300 text-emerald-950' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    @click="selectTab(tab.key)"
                    x-text="tab.label"
                ></button>
            </template>
        </div>
    </section>

    <div x-show="loading" class="grid gap-3">
        <template x-for="i in 5" :key="i">
            <div class="h-16 animate-pulse rounded-3xl bg-white/70"></div>
        </template>
    </div>

    <x-table x-show="!loading">
        <thead class="bg-emerald-950 text-left text-xs font-bold uppercase tracking-[0.18em] text-emerald-50">
            <tr>
                <template x-for="column in currentColumns()" :key="column.key">
                    <th class="px-5 py-4" x-text="column.label"></th>
                </template>
                <th class="px-5 py-4 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white/70 text-sm">
            <template x-for="row in items" :key="row.id">
                <tr class="transition hover:bg-amber-50/70">
                    <template x-for="column in currentColumns()" :key="column.key">
                        <td class="px-5 py-4 align-top">
                            <template x-if="column.type === 'status'">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide" :class="statusClass(valueFor(row, column))" x-text="formatValue(valueFor(row, column), column)"></span>
                            </template>
                            <template x-if="column.type !== 'status'">
                                <span class="font-medium text-slate-700" x-text="formatValue(valueFor(row, column), column)"></span>
                            </template>
                        </td>
                    </template>
                    <td class="px-5 py-4 text-right">
                        <div class="flex justify-end gap-2">
                            <button class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700" @click="openDetail(row)">Detail</button>
                            <button class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700" x-show="canEditCurrent()" @click="openEdit(row)">Edit</button>
                            <template x-for="action in rowActions(row)" :key="action.key">
                                <button class="rounded-xl px-3 py-2 text-xs font-bold" :class="action.class" @click="runRowAction(action, row)" x-text="action.label"></button>
                            </template>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </x-table>

    <div x-show="!loading && items.length === 0" class="rounded-[2rem] border border-dashed border-emerald-950/20 bg-white/70 p-10 text-center">
        <p class="font-display text-3xl text-slate-900">Belum ada data.</p>
        <p class="mt-2 text-sm text-slate-500">Coba ubah pencarian, refresh, atau buat data baru jika modul ini mendukung create.</p>
    </div>

    <x-pagination />
</div>
