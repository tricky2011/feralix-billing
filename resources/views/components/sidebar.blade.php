<div>
    <div
        x-show="sidebarOpen"
        x-cloak
        class="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-sm lg:hidden"
        @click="sidebarOpen = false"
    ></div>

    <aside
        class="fixed inset-y-0 left-0 z-50 w-80 transform border-r border-slate-200/80 bg-white/95 px-4 py-5 text-slate-700 shadow-2xl shadow-slate-950/10 backdrop-blur-xl transition-transform duration-300 dark:border-white/10 dark:bg-[#0b1628]/95 dark:text-slate-300 dark:shadow-black/30 lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    >
        <div class="flex h-full flex-col">
            <div class="flex items-center justify-between gap-3 rounded-3xl border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/[0.04]">
                <a href="{{ route('admin.panel', ['page' => 'dashboard']) }}" class="flex min-w-0 items-center gap-3" @click="sidebarOpen = false">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-blue-600 text-sm font-black text-white shadow-lg shadow-blue-600/25">FX</span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-black tracking-tight text-slate-950 dark:text-white" x-text="theme === 'dark' ? 'Feralix Billing' : 'Feralix ISP Cloud'"></span>
                        <span class="block truncate text-xs text-slate-500 dark:text-slate-400">Billing & network ops</span>
                    </span>
                </a>

                <button
                    type="button"
                    class="grid h-10 w-10 place-items-center rounded-2xl border border-slate-200 text-slate-500 transition hover:border-blue-200 hover:text-blue-700 dark:border-white/10 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white lg:hidden"
                    @click="sidebarOpen = false"
                    aria-label="Tutup sidebar"
                >
                    <span class="text-lg leading-none">&times;</span>
                </button>
            </div>

            <nav class="mt-5 flex-1 space-y-2 overflow-y-auto pr-1">
                <template x-for="group in visibleSidebarGroups()" :key="group.key">
                    <section class="rounded-2xl">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-xl px-2.5 py-2 text-left text-[11px] font-black uppercase tracking-[0.18em] text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-500 dark:hover:bg-white/[0.05] dark:hover:text-slate-200"
                            @click="toggleGroup(group.key)"
                        >
                            <span x-text="groupLabel(group)"></span>
                            <span class="text-sm transition" :class="groupOpen(group.key) ? 'rotate-90 text-blue-500' : 'text-slate-400'">›</span>
                        </button>

                        <div x-show="groupOpen(group.key)" class="space-y-1">
                            <template x-for="item in group.items" :key="`${item.page}-${item.tab ?? 'index'}`">
                                <a
                                    :href="menuItemUrl(item)"
                                    class="group flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-semibold transition"
                                    :class="isMenuItemActive(item) ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/20' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-white/[0.06] dark:hover:text-white'"
                                    @click="sidebarOpen = false"
                                >
                                    <span
                                        class="grid h-8 w-8 shrink-0 place-items-center rounded-xl border text-[11px] font-black"
                                        :class="isMenuItemActive(item) ? 'border-white/20 bg-white/15 text-white' : 'border-slate-200 bg-white text-slate-500 group-hover:border-blue-200 group-hover:text-blue-600 dark:border-white/10 dark:bg-white/[0.04] dark:text-slate-400 dark:group-hover:text-blue-200'"
                                        x-text="item.icon"
                                    ></span>
                                    <span class="min-w-0 flex-1 truncate" x-text="itemLabel(item)"></span>

                                    <template x-if="item.badgeKey">
                                        <span
                                            class="min-w-6 rounded-full px-2 py-0.5 text-center text-[11px] font-black"
                                            :class="isMenuItemActive(item) ? 'bg-white/20 text-white' : 'bg-blue-50 text-blue-700 ring-1 ring-blue-100 dark:bg-blue-500/15 dark:text-blue-200 dark:ring-blue-400/20'"
                                            x-text="badgeLabel(item)"
                                        ></span>
                                    </template>
                                </a>
                            </template>
                        </div>
                    </section>
                </template>
            </nav>

            <div class="mt-4 rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/[0.04]">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-700 dark:text-blue-300" x-text="ui('footerTitle')"></p>
                    <span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-black text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-400/20">API v1</span>
                </div>
                <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400" x-text="ui('footerBody')"></p>
            </div>
        </div>
    </aside>
</div>
