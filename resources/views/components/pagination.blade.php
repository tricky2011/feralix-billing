<div class="flex flex-col gap-3 rounded-3xl border border-emerald-950/10 bg-white/80 p-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
    <p>
        <span x-text="pagination.from ?? 0"></span>-<span x-text="pagination.to ?? 0"></span>
        dari <span x-text="pagination.total ?? 0"></span> data
    </p>
    <div class="flex items-center gap-2">
        <button class="rounded-2xl border border-slate-200 px-4 py-2 font-semibold disabled:opacity-40" @click="changePage((pagination.current_page ?? 1) - 1)" :disabled="(pagination.current_page ?? 1) <= 1">Prev</button>
        <span class="rounded-2xl bg-emerald-950 px-4 py-2 font-bold text-white">
            <span x-text="pagination.current_page ?? 1"></span>/<span x-text="pagination.last_page ?? 1"></span>
        </span>
        <button class="rounded-2xl border border-slate-200 px-4 py-2 font-semibold disabled:opacity-40" @click="changePage((pagination.current_page ?? 1) + 1)" :disabled="(pagination.current_page ?? 1) >= (pagination.last_page ?? 1)">Next</button>
    </div>
</div>
