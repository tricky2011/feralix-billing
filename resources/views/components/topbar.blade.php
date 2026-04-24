<header class="fixed left-0 right-0 top-0 z-30 border-b border-emerald-950/10 bg-[#f8f3e9]/85 backdrop-blur-xl lg:left-72">
    <div class="flex h-20 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.3em] text-emerald-700" x-text="currentSectionLabel()"></p>
            <h1 class="font-display text-3xl leading-none text-slate-950" x-text="currentTitle()"></h1>
        </div>

        <div class="flex items-center gap-3">
            <template x-if="routerSwitcher.enabled">
                <select
                    class="hidden rounded-2xl border border-emerald-950/10 bg-white/80 px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm outline-none transition focus:border-emerald-700 md:block"
                    x-model="routerSwitcher.active_router_id"
                    @change="switchRouter"
                >
                    <template x-for="router in routerSwitcher.available_routers" :key="router.id ?? 'all'">
                        <option :value="router.id ?? ''" x-text="router.router_name"></option>
                    </template>
                </select>
            </template>

            <div class="hidden items-center gap-3 rounded-2xl border border-emerald-950/10 bg-white/80 px-4 py-2 shadow-sm sm:flex">
                <div class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-950 text-sm font-bold text-white" x-text="userInitials()"></div>
                <div>
                    <p class="text-sm font-bold text-slate-900" x-text="user?.name ?? 'Memuat...'"></p>
                    <p class="text-xs uppercase tracking-wider text-slate-500" x-text="user?.role ?? 'session'"></p>
                </div>
            </div>

            <button
                type="button"
                class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 transition hover:bg-red-100"
                @click="logout"
            >
                Logout
            </button>
        </div>
    </div>
</header>
