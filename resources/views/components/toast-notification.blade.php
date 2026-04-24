<div class="fixed bottom-5 right-5 z-[60] grid gap-3">
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition
            class="w-80 rounded-3xl border p-4 shadow-2xl"
            :class="toast.type === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-900'"
        >
            <p class="text-sm font-bold" x-text="toast.title"></p>
            <p class="mt-1 text-sm" x-text="toast.message"></p>
        </div>
    </template>
</div>
