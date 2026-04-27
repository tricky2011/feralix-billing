<div class="space-y-5">
    <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.24em] text-blue-700 dark:text-blue-300" x-text="currentSectionLabel()"></p>
                <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-white" x-text="currentTitle()"></h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400" x-text="currentDescription()"></p>
            </div>
            <div x-show="!isPlaceholderCurrent()" class="flex flex-col gap-3 sm:flex-row">
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
                    type="button"
                    class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-blue-600/20 disabled:hidden"
                    @click="openCreate"
                    :disabled="!canCreateCurrent()"
                >
                    Tambah
                </button>
            </div>
        </div>

        <div x-show="hasTabs() && !isPlaceholderCurrent()" class="mt-5 flex flex-wrap gap-2">
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

    <div x-show="loading && !isPlaceholderCurrent()" class="grid gap-3">
        <template x-for="i in 5" :key="i">
            <div class="h-16 animate-pulse rounded-3xl bg-white/70 dark:bg-white/[0.05]"></div>
        </template>
    </div>

    <x-table x-show="!loading && !isPlaceholderCurrent()">
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
                            <button class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 dark:border-white/10 dark:text-slate-300" x-show="canEditCurrent()" @click="openEdit(row)">Edit</button>
                            <template x-for="action in rowActions(row)" :key="action.key">
                                <button class="rounded-xl px-3 py-2 text-xs font-bold" :class="action.class" @click="runRowAction(action, row)" x-text="action.label"></button>
                            </template>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </x-table>

    <div x-show="!loading && !isPlaceholderCurrent() && items.length === 0" class="rounded-[2rem] border border-dashed border-slate-300 bg-white/70 p-10 text-center dark:border-white/10 dark:bg-white/[0.04]">
        <p class="text-2xl font-black text-slate-900 dark:text-white">Belum ada data.</p>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Coba ubah pencarian, refresh, atau buat data baru jika modul ini mendukung create.</p>
    </div>

    <div x-show="!isPlaceholderCurrent()">
        <x-pagination />
    </div>
</div>
