<div
    x-show="modal.open || confirm.open || detail.open"
    x-cloak
    class="fixed inset-0 z-50 grid place-items-center bg-emerald-950/45 p-4 backdrop-blur-sm"
    @keydown.escape.window="closeModals"
>
    <div class="absolute inset-0" @click="closeModals"></div>

    <section
        x-show="modal.open"
        x-transition
        class="relative max-h-[90vh] w-full max-w-3xl overflow-hidden rounded-[2rem] bg-[#fbf7ef] shadow-2xl"
    >
        <header class="border-b border-emerald-950/10 px-6 py-5">
            <p class="text-xs font-bold uppercase tracking-[0.28em] text-emerald-700">Form</p>
            <h2 class="mt-2 font-display text-3xl" x-text="modal.title"></h2>
        </header>

        <form class="max-h-[70vh] overflow-y-auto p-6" @submit.prevent="submitModal">
            <div class="grid gap-4 sm:grid-cols-2">
                <template x-for="field in modal.fields" :key="field.name">
                    <label class="block" :class="field.type === 'textarea' ? 'sm:col-span-2' : ''">
                        <span class="text-sm font-bold text-slate-700" x-text="field.label"></span>
                        <template x-if="field.type === 'select'">
                            <select class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-emerald-700" x-model="modal.form[field.name]">
                                <template x-for="option in field.options ?? []" :key="option.value">
                                    <option :value="option.value" x-text="option.label"></option>
                                </template>
                            </select>
                        </template>
                        <template x-if="field.type === 'multiselect'">
                            <select multiple class="mt-2 min-h-36 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-emerald-700" x-model="modal.form[field.name]">
                                <template x-for="option in (field.options ?? []).filter((item) => item.value !== '')" :key="option.value">
                                    <option :value="option.value" x-text="option.label"></option>
                                </template>
                            </select>
                        </template>
                        <template x-if="field.type === 'textarea'">
                            <textarea class="mt-2 min-h-28 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-emerald-700" x-model="modal.form[field.name]" :placeholder="field.placeholder ?? ''"></textarea>
                        </template>
                        <template x-if="!['select','multiselect','textarea'].includes(field.type)">
                            <input class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-emerald-700" :type="field.type ?? 'text'" x-model="modal.form[field.name]" :placeholder="field.placeholder ?? ''" :max="field.max ?? null" :min="field.min ?? null">
                        </template>
                        <p class="mt-1 text-xs text-red-600" x-show="modal.errors[field.name]" x-text="firstError(field.name)"></p>
                    </label>
                </template>
            </div>

            <div x-show="modal.message" class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="modal.message"></div>

            <footer class="mt-6 flex justify-end gap-3">
                <button type="button" class="rounded-2xl border border-slate-200 px-5 py-3 text-sm font-bold" @click="closeModals">Batal</button>
                <button type="submit" class="rounded-2xl bg-emerald-950 px-5 py-3 text-sm font-bold text-white disabled:opacity-50" :disabled="saving">
                    <span x-show="!saving">Simpan</span>
                    <span x-show="saving" x-cloak>Menyimpan...</span>
                </button>
            </footer>
        </form>
    </section>

    <section x-show="confirm.open" x-transition class="relative w-full max-w-md rounded-[2rem] bg-white p-6 shadow-2xl">
        <p class="text-xs font-bold uppercase tracking-[0.28em] text-red-700">Konfirmasi</p>
        <h2 class="mt-2 font-display text-3xl" x-text="confirm.title"></h2>
        <p class="mt-3 text-sm leading-6 text-slate-600" x-text="confirm.message"></p>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" class="rounded-2xl border border-slate-200 px-5 py-3 text-sm font-bold" @click="closeModals">Batal</button>
            <button type="button" class="rounded-2xl bg-red-700 px-5 py-3 text-sm font-bold text-white" @click="runConfirm">Ya, lanjut</button>
        </div>
    </section>

    <section x-show="detail.open" x-transition class="relative max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-[2rem] bg-white shadow-2xl">
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.28em] text-emerald-700">Detail</p>
                <h2 class="mt-2 font-display text-3xl" x-text="detail.title"></h2>
            </div>
            <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-bold" @click="closeModals">Tutup</button>
        </header>
        <div class="max-h-[72vh] overflow-y-auto p-6">
            <dl class="grid gap-4 sm:grid-cols-2">
                <template x-for="row in detailRows()" :key="row.label">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <dt class="text-xs font-bold uppercase tracking-wider text-slate-500" x-text="row.label"></dt>
                        <dd class="mt-2 break-words text-sm font-semibold text-slate-900" x-text="row.value"></dd>
                    </div>
                </template>
            </dl>

            <div x-show="page === 'tickets'" class="mt-6 grid gap-4 rounded-[1.5rem] border border-emerald-950/10 bg-emerald-50/60 p-4">
                <div class="grid gap-4 md:grid-cols-[220px_1fr]">
                    <label>
                        <span class="text-sm font-bold text-slate-700">Update status</span>
                        <select class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-emerald-700" x-model="detail.status">
                            <template x-for="status in ticketStatusOptions" :key="status.value">
                                <option :value="status.value" x-text="status.label"></option>
                            </template>
                        </select>
                    </label>
                    <label>
                        <span class="text-sm font-bold text-slate-700">Reply / comment</span>
                        <textarea class="mt-2 min-h-24 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm outline-none focus:border-emerald-700" x-model="detail.reply" placeholder="Tulis update untuk ticket ini..."></textarea>
                    </label>
                </div>
                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold" @click="updateTicketStatus">Update Status</button>
                    <button type="button" class="rounded-2xl bg-emerald-950 px-4 py-3 text-sm font-bold text-white" @click="createTicketReply">Kirim Reply</button>
                </div>

                <div x-show="ticketReplies().length > 0" class="grid gap-3">
                    <template x-for="reply in ticketReplies()" :key="reply.id">
                        <article class="rounded-2xl bg-white p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-bold text-slate-900" x-text="reply.user?.name ?? 'System'"></p>
                                <p class="text-xs text-slate-500" x-text="formatValue(reply.created_at)"></p>
                            </div>
                            <p class="mt-2 whitespace-pre-line text-sm text-slate-600" x-text="reply.body"></p>
                        </article>
                    </template>
                </div>
            </div>
        </div>
    </section>
</div>
