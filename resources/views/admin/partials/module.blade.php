<div class="space-y-5">
    <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.24em] text-blue-700 dark:text-blue-300" x-text="currentSectionLabel()"></p>
                <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950 dark:text-white" x-text="currentTitle()"></h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400" x-text="currentDescription()"></p>
            </div>
            <div x-show="!isPlaceholderCurrent() && page !== 'technician-dashboard' && page !== 'router-sync'" class="flex flex-col gap-3 sm:flex-row">
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

        <div x-show="hasTabs() && !isPlaceholderCurrent() && page !== 'technician-dashboard' && page !== 'router-sync'" class="mt-5 flex flex-wrap gap-2">
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

    <div x-show="loading && !isPlaceholderCurrent() && page !== 'technician-dashboard' && page !== 'router-sync'" class="grid gap-3">
        <template x-for="i in 5" :key="i">
            <div class="h-16 animate-pulse rounded-3xl bg-white/70 dark:bg-white/[0.05]"></div>
        </template>
    </div>

    <x-table x-show="!loading && !isPlaceholderCurrent() && page !== 'technician-dashboard' && page !== 'router-sync'">
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

    <div x-show="!loading && !isPlaceholderCurrent() && page !== 'technician-dashboard' && page !== 'router-sync' && items.length === 0" class="rounded-[2rem] border border-dashed border-slate-300 bg-white/70 p-10 text-center dark:border-white/10 dark:bg-white/[0.04]">
        <p class="text-2xl font-black text-slate-900 dark:text-white">Belum ada data.</p>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Coba ubah pencarian, refresh, atau buat data baru jika modul ini mendukung create.</p>
    </div>

    <div x-show="!isPlaceholderCurrent() && page !== 'technician-dashboard' && page !== 'router-sync'">
        <x-pagination />
    </div>
</div>
