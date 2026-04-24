<aside
    class="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-emerald-950/10 bg-emerald-950 px-4 py-5 text-stone-100 shadow-2xl shadow-emerald-950/20 lg:block"
>
    <div class="flex h-full flex-col">
        <a href="{{ route('admin.panel', ['page' => 'dashboard']) }}" class="flex items-center gap-3 rounded-3xl bg-white/10 p-3">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-amber-300 font-black text-emerald-950">FX</span>
            <span>
                <span class="block text-sm font-bold uppercase tracking-[0.22em] text-emerald-100">Feralix</span>
                <span class="block text-xs text-stone-300">Billing ISP Panel</span>
            </span>
        </a>

        <nav class="mt-8 space-y-1">
            <template x-for="item in visibleMenu()" :key="item.key">
                <a
                    :href="adminUrl(item.key)"
                    class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold transition"
                    :class="page === item.key ? 'bg-amber-300 text-emerald-950 shadow-lg shadow-amber-900/20' : 'text-stone-300 hover:bg-white/10 hover:text-white'"
                >
                    <span class="text-lg" x-text="item.icon"></span>
                    <span x-text="item.label"></span>
                </a>
            </template>
        </nav>

        <div class="mt-auto rounded-3xl border border-white/10 bg-white/10 p-4">
            <p class="text-xs uppercase tracking-[0.28em] text-emerald-200">Network mood</p>
            <p class="mt-2 text-sm text-stone-200">Operasional cepat, billing rapi, isolir tetap jinak.</p>
        </div>
    </div>
</aside>
