<header class="fixed left-0 right-0 top-0 z-30 border-b border-slate-200/80 bg-[#F8FAFC]/85 backdrop-blur-xl transition-colors dark:border-white/10 dark:bg-[#07111f]/85 lg:left-80">
    <div class="flex h-20 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
        <div class="flex min-w-0 items-center gap-3">
            <button
                type="button"
                class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-blue-200 hover:text-blue-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300 dark:hover:text-white lg:hidden"
                @click="sidebarOpen = true"
                aria-label="Buka sidebar"
            >
                <span class="text-xl leading-none">☰</span>
            </button>

            <div class="hidden min-w-0 lg:block">
                <p class="truncate text-xs font-black uppercase tracking-[0.24em] text-blue-700 dark:text-blue-300" x-text="currentSectionLabel()"></p>
                <h1 class="truncate text-xl font-black tracking-tight text-slate-950 dark:text-white" x-text="currentTitle()"></h1>
            </div>

            <div class="relative w-[15rem] sm:w-[22rem] xl:w-[30rem]">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-black text-slate-400">/</span>
                <input
                    type="search"
                    class="h-11 w-full rounded-2xl border border-slate-200 bg-white pl-10 pr-4 text-sm font-semibold text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-300 focus:ring-4 focus:ring-blue-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-blue-400 dark:focus:ring-blue-500/10"
                    x-model.debounce.400ms="globalSearch"
                    :placeholder="ui('searchPlaceholder')"
                    @keydown.enter.prevent="applyGlobalSearch"
                >
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <template x-if="routerSwitcher.enabled">
                <select
                    class="hidden max-w-[12rem] rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 shadow-sm outline-none transition focus:border-blue-300 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-200 xl:block"
                    x-model="routerSwitcher.active_router_id"
                    @change="switchRouter"
                    aria-label="Pilih distribusi/router"
                >
                    <template x-for="router in routerSwitcher.available_routers" :key="router.id ?? 'all'">
                        <option :value="router.id ?? ''" x-text="router.router_name"></option>
                    </template>
                </select>
            </template>

            <div class="hidden rounded-2xl border border-slate-200 bg-white p-1 shadow-sm dark:border-white/10 dark:bg-white/[0.04] md:flex">
                <button
                    type="button"
                    class="rounded-xl px-2.5 py-1.5 text-xs font-black transition"
                    :class="language === 'en' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                    @click="setLanguage('en')"
                >
                    EN
                </button>
                <button
                    type="button"
                    class="rounded-xl px-2.5 py-1.5 text-xs font-black transition"
                    :class="language === 'id' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                    @click="setLanguage('id')"
                >
                    ID
                </button>
            </div>

            <button
                type="button"
                class="hidden rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-black text-slate-600 shadow-sm transition hover:border-blue-200 hover:text-blue-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300 dark:hover:text-white sm:block"
                @click="toggleTheme"
                x-text="theme === 'dark' ? ui('dark') : ui('light')"
            ></button>

            <button
                type="button"
                class="grid h-11 w-11 place-items-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-blue-200 hover:text-blue-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300 dark:hover:text-white"
                @click="refreshCurrentPage"
                :aria-label="ui('refresh')"
            >
                <span class="text-lg leading-none">↻</span>
            </button>

            <button
                type="button"
                class="relative grid h-11 w-11 place-items-center rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-blue-200 hover:text-blue-700 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-300 dark:hover:text-white"
                :aria-label="ui('notifications')"
            >
                <span class="text-xs font-black">N</span>
                <span
                    class="absolute -right-1 -top-1 min-w-5 rounded-full bg-blue-600 px-1.5 py-0.5 text-center text-[10px] font-black text-white"
                    x-text="totalNotifications() > 99 ? '99+' : totalNotifications()"
                ></span>
            </button>

            <div class="relative" @click.outside="userMenuOpen = false">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 pr-3 shadow-sm transition hover:border-blue-200 dark:border-white/10 dark:bg-white/[0.04]"
                    @click="userMenuOpen = !userMenuOpen"
                >
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-blue-600 text-sm font-bold text-white" x-text="userInitials()"></span>
                    <span class="hidden text-left xl:block">
                        <span class="block max-w-32 truncate text-sm font-bold text-slate-900 dark:text-white" x-text="user?.name ?? 'Memuat...'"></span>
                        <span class="block text-[11px] font-black uppercase tracking-wider text-blue-600 dark:text-blue-300" x-text="roleLabel()"></span>
                    </span>
                </button>

                <div
                    x-show="userMenuOpen"
                    x-cloak
                    class="absolute right-0 mt-3 w-72 rounded-3xl border border-slate-200 bg-white p-3 shadow-2xl shadow-slate-950/10 dark:border-white/10 dark:bg-[#0b1628] dark:shadow-black/30"
                >
                    <div class="rounded-2xl bg-slate-50 p-3 dark:bg-white/[0.04]">
                        <p class="text-sm font-bold text-slate-900 dark:text-white" x-text="user?.name ?? '-'"></p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-text="user?.username ?? user?.email ?? '-'"></p>
                        <p class="mt-2 inline-flex rounded-full bg-blue-50 px-2 py-1 text-[11px] font-black uppercase tracking-wide text-blue-700 ring-1 ring-blue-100 dark:bg-blue-500/10 dark:text-blue-200 dark:ring-blue-400/20" x-text="roleLabel()"></p>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 md:hidden">
                        <button type="button" class="rounded-2xl border border-slate-200 px-3 py-2 text-xs font-black dark:border-white/10" @click="setLanguage(language === 'en' ? 'id' : 'en')" x-text="language === 'en' ? 'ID' : 'EN'"></button>
                        <button type="button" class="rounded-2xl border border-slate-200 px-3 py-2 text-xs font-black dark:border-white/10" @click="toggleTheme" x-text="theme === 'dark' ? ui('dark') : ui('light')"></button>
                    </div>

                    <button
                        type="button"
                        class="mt-3 w-full rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 transition hover:bg-red-100 dark:border-red-400/20 dark:bg-red-500/10 dark:text-red-200"
                        @click="logout"
                    >
                        Logout
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>
