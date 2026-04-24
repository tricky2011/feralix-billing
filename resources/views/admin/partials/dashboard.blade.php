<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <template x-for="card in dashboardCards()" :key="card.key">
            <article class="relative overflow-hidden rounded-[1.5rem] border border-emerald-950/10 bg-white/80 p-5 shadow-sm">
                <div class="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-amber-300/20"></div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500" x-text="card.label"></p>
                <p class="mt-4 text-3xl font-black text-slate-950" x-text="card.value"></p>
                <p class="mt-2 text-xs text-slate-500" x-text="card.meta"></p>
            </article>
        </template>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.4fr_0.9fr]">
        <section class="rounded-[2rem] border border-emerald-950/10 bg-white/80 p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.28em] text-emerald-700">Revenue</p>
                    <h2 class="mt-2 font-display text-3xl">Pendapatan bulanan</h2>
                </div>
                <button class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold" @click="loadPage">Refresh</button>
            </div>
            <div class="mt-8 flex h-72 items-end gap-3">
                <template x-for="point in revenuePoints()" :key="point.period">
                    <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                        <div class="flex h-56 w-full items-end rounded-t-2xl bg-emerald-950/5">
                            <div class="w-full rounded-t-2xl bg-emerald-800 transition-all" :style="`height: ${barHeight(point.revenue, revenueMax())}%`"></div>
                        </div>
                        <p class="w-full truncate text-center text-xs text-slate-500" x-text="point.label"></p>
                    </div>
                </template>
            </div>
        </section>

        <section class="rounded-[2rem] border border-emerald-950/10 bg-white/80 p-6 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-[0.28em] text-emerald-700">PPP Active</p>
            <h2 class="mt-2 font-display text-3xl">Trend monitor</h2>
            <div class="mt-8 space-y-3">
                <template x-for="point in pppPoints()" :key="point.date">
                    <div>
                        <div class="flex items-center justify-between text-xs font-bold text-slate-500">
                            <span x-text="point.label"></span>
                            <span x-text="point.active"></span>
                        </div>
                        <div class="mt-2 h-3 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-amber-500" :style="`width: ${barHeight(point.active, pppMax())}%`"></div>
                        </div>
                    </div>
                </template>
            </div>
        </section>
    </div>

    <section class="rounded-[2rem] border border-emerald-950/10 bg-white/80 p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.28em] text-emerald-700">Teknisi</p>
        <h2 class="mt-2 font-display text-3xl">Ranking penyelesaian</h2>
        <div class="mt-6 grid gap-3 md:grid-cols-3">
            <template x-for="tech in technicianRanking()" :key="tech.technician_id ?? tech.id ?? tech.full_name">
                <div class="rounded-3xl border border-slate-200 bg-white p-4">
                    <p class="font-bold text-slate-900" x-text="tech.full_name ?? tech.technician_name ?? 'Teknisi'"></p>
                    <p class="mt-2 text-sm text-slate-500">
                        <span x-text="tech.completed_work_orders ?? tech.completed ?? 0"></span> WO selesai
                    </p>
                </div>
            </template>
        </div>
        <div x-show="technicianRanking().length === 0" class="mt-6 rounded-3xl bg-slate-50 p-6 text-sm text-slate-500">
            Belum ada data ranking teknisi.
        </div>
    </section>
</div>
