<div class="space-y-6">
    <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-blue-700 ring-1 ring-blue-100 dark:bg-blue-500/10 dark:text-blue-200 dark:ring-blue-400/20" x-text="roleLabel()"></span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500 dark:bg-white/[0.06] dark:text-slate-400">Feralix ISP Cloud</span>
                </div>
                <h2 class="mt-4 text-3xl font-black tracking-tight text-slate-950 dark:text-white lg:text-4xl" x-text="language === 'id' ? 'Pusat operasi ISP' : 'ISP operations center'"></h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400" x-text="language === 'id' ? 'Billing, jaringan, isolir, helpdesk, dan aktivitas teknisi dalam satu tampilan operasional.' : 'Billing, network, isolation, helpdesk, and technician activity in one operational view.'"></p>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:flex">
                <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-black text-slate-700 transition hover:border-blue-200 hover:text-blue-700 dark:border-white/10 dark:text-slate-300 dark:hover:text-white" @click="setLanguage(language === 'en' ? 'id' : 'en')">
                    <span x-text="language === 'en' ? 'EN / ID' : 'ID / EN'"></span>
                </button>
                <button type="button" class="rounded-2xl bg-blue-600 px-4 py-2 text-sm font-black text-white shadow-lg shadow-blue-600/20" @click="refreshCurrentPage" x-text="ui('refresh')"></button>
            </div>
        </div>
    </section>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <template x-for="card in dashboardCards()" :key="card.key">
            <article class="rounded-[1.5rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                <div class="flex items-center justify-between gap-4">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400" x-text="card.label"></p>
                    <span class="h-2.5 w-2.5 rounded-full ring-4" :class="kpiToneClass(card.tone)"></span>
                </div>
                <p class="mt-5 text-3xl font-black tracking-tight text-slate-950 dark:text-white" x-text="card.value"></p>
                <p class="mt-2 text-xs font-semibold text-slate-500 dark:text-slate-400" x-text="card.meta"></p>
            </article>
        </template>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.35fr_0.85fr]">
        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-700 dark:text-blue-300" x-text="ui('revenueChart')"></p>
                    <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white" x-text="language === 'id' ? 'Tren koleksi pembayaran' : 'Payment collection trend'"></h3>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-500 dark:bg-white/[0.06] dark:text-slate-400">API v1</span>
            </div>

            <div class="mt-8 flex h-72 items-end gap-3 rounded-3xl border border-slate-100 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-[#07111f]/70">
                <template x-for="point in revenuePoints().slice(-12)" :key="point.period">
                    <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                        <div class="flex h-56 w-full items-end rounded-t-2xl bg-white shadow-inner dark:bg-white/[0.04]">
                            <div class="w-full rounded-t-2xl bg-gradient-to-t from-blue-700 to-cyan-400 transition-all" :style="`height: ${barHeight(point.revenue, revenueMax())}%`"></div>
                        </div>
                        <p class="w-full truncate text-center text-[11px] font-bold text-slate-400" x-text="point.label"></p>
                    </div>
                </template>
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-700 dark:text-blue-300" x-text="ui('networkStatus')"></p>
            <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">PPPoE / NAS</h3>

            <div class="mt-6 space-y-4">
                <template x-for="item in networkSummaryCards()" :key="item.key">
                    <div class="rounded-3xl border border-slate-100 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-[#07111f]/70">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-black text-slate-900 dark:text-white" x-text="item.label"></p>
                                <p class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400" x-text="item.meta"></p>
                            </div>
                            <span class="text-sm font-black text-slate-700 dark:text-slate-200" x-text="item.value"></span>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
                            <div class="h-full rounded-full" :class="item.class" :style="`width: ${item.percent}%`"></div>
                        </div>
                    </div>
                </template>
            </div>
        </section>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <section class="rounded-[2rem] border border-slate-200 bg-white shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 p-5 dark:border-white/10">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-700 dark:text-blue-300" x-text="ui('invoiceTable')"></p>
                    <h3 class="mt-1 text-xl font-black tracking-tight text-slate-950 dark:text-white">Invoice queue</h3>
                </div>
                <a href="/admin/billing" class="rounded-2xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-600 transition hover:border-blue-200 hover:text-blue-700 dark:border-white/10 dark:text-slate-300 dark:hover:text-white">Open</a>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-[0.16em] text-slate-400 dark:bg-white/[0.03]">
                        <tr>
                            <th class="px-5 py-4">Invoice</th>
                            <th class="px-5 py-4" x-text="language === 'id' ? 'Pelanggan' : 'Customer'"></th>
                            <th class="px-5 py-4" x-text="language === 'id' ? 'Paket' : 'Plan'"></th>
                            <th class="px-5 py-4" x-text="language === 'id' ? 'Nominal' : 'Amount'"></th>
                            <th class="px-5 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                        <template x-for="invoice in dashboardInvoiceRows()" :key="invoice.id ?? invoice.invoice">
                            <tr class="transition hover:bg-blue-50/60 dark:hover:bg-white/[0.04]">
                                <td class="px-5 py-4 font-black text-slate-900 dark:text-white" x-text="invoice.invoice"></td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300" x-text="invoice.customer"></td>
                                <td class="px-5 py-4 text-slate-500 dark:text-slate-400" x-text="invoice.plan"></td>
                                <td class="px-5 py-4 font-bold text-slate-700 dark:text-slate-200" x-text="invoice.amount"></td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide" :class="statusClass(invoice.status)" x-text="invoiceStatusLabel(invoice.status)"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div x-show="dashboardInvoiceRows().length === 0" class="p-8 text-center text-sm font-semibold text-slate-500 dark:text-slate-400" x-text="ui('noInvoices')"></div>
        </section>

        <div class="space-y-6">
            <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-700 dark:text-blue-300" x-text="ui('helpdeskSummary')"></p>
                <div class="mt-5 grid grid-cols-3 gap-3">
                    <template x-for="item in helpdeskCards()" :key="item.key">
                        <div class="rounded-3xl border border-slate-100 bg-slate-50 p-4 dark:border-white/10 dark:bg-[#07111f]/70">
                            <p class="text-2xl font-black text-slate-950 dark:text-white" x-text="item.value"></p>
                            <p class="mt-1 text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400" x-text="item.label"></p>
                        </div>
                    </template>
                </div>

                <div class="mt-5 space-y-3">
                    <template x-for="tech in technicianRanking().slice(0, 3)" :key="tech.technician_id ?? tech.id ?? tech.full_name">
                        <div class="flex items-center justify-between gap-4 rounded-2xl bg-slate-50 px-4 py-3 dark:bg-[#07111f]/70">
                            <span class="truncate text-sm font-bold text-slate-700 dark:text-slate-200" x-text="tech.full_name ?? tech.technician_name ?? 'Technician'"></span>
                            <span class="text-xs font-black text-blue-700 dark:text-blue-300"><span x-text="tech.completed_work_orders ?? tech.completed ?? 0"></span> WO</span>
                        </div>
                    </template>
                </div>
            </section>

            <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-700 dark:text-blue-300" x-text="ui('recentActivity')"></p>
                <div class="mt-5 space-y-3">
                    <template x-for="activity in activityRows()" :key="`${activity.type}-${activity.title}`">
                        <div class="flex gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-3 dark:border-white/10 dark:bg-[#07111f]/70">
                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-blue-500"></span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-slate-900 dark:text-white" x-text="activity.title"></p>
                                <p class="mt-1 truncate text-xs font-semibold text-slate-500 dark:text-slate-400"><span x-text="activity.type"></span> - <span x-text="activity.meta"></span></p>
                            </div>
                        </div>
                    </template>
                </div>

                <div x-show="activityRows().length === 0" class="mt-5 rounded-2xl bg-slate-50 p-4 text-sm font-semibold text-slate-500 dark:bg-[#07111f]/70 dark:text-slate-400">
                    No audit activity loaded yet.
                </div>
            </section>
        </div>
    </div>
</div>
