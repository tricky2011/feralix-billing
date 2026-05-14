<!-- Voucher Detail Slide-over Panel -->
<div
    x-show="voucherDetail.open"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="translate-x-full opacity-0"
    x-transition:enter-end="translate-x-0 opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="translate-x-0 opacity-100"
    x-transition:leave-end="translate-x-full opacity-0"
    @click.self="voucherDetail.open = false"
    class="fixed inset-0 z-50 flex justify-end"
    style="display: none;"
>
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>

    <!-- Slide-over panel -->
    <div class="relative flex h-full w-full max-w-md flex-col border-l border-white/10 bg-[#0f172a] shadow-2xl shadow-black/50 lg:max-w-lg" style="border-radius: 1.5rem 0 0 0;">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-white/10 px-6 py-5">
            <div>
                <h2 class="text-lg font-black text-white">Detail Voucher</h2>
                <p x-show="voucherDetail.voucher" class="mt-0.5 text-sm text-slate-400" x-text="voucherDetail.voucher?.username ?? '-'"></p>
            </div>
            <button @click="voucherDetail.open = false" class="rounded-xl p-2 text-slate-400 transition hover:bg-white/10 hover:text-white">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Loading state -->
        <div x-show="voucherDetail.loading" class="flex flex-1 items-center justify-center">
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-600 border-t-sky-400"></div>
        </div>

        <!-- Content -->
        <div x-show="!voucherDetail.loading && voucherDetail.voucher" class="flex-1 overflow-y-auto px-6 py-5">
            <!-- Status badge -->
            <div class="mb-6 flex items-center justify-between">
                <span class="rounded-full px-3 py-1 text-xs font-black uppercase" :class="statusClass(voucherDetail.voucher?.status)" x-text="voucherDetail.voucher?.status ?? '-'"></span>
                <span class="text-xs text-slate-400" x-text="'ID: ' + (voucherDetail.voucher?.id ?? '-')"></span>
            </div>

            <!-- Info section -->
            <div class="rounded-2xl border border-white/10 bg-white/5 p-5">
                <h3 class="mb-4 text-xs font-black uppercase tracking-widest text-slate-400">Info Voucher</h3>
                <dl class="space-y-3.5">
                    <div class="flex justify-between">
                        <dt class="text-sm text-slate-400">Kode Voucher</dt>
                        <dd class="font-mono font-bold text-sky-400" x-text="voucherDetail.voucher?.voucher_code ?? '-'"></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-slate-400">Username</dt>
                        <dd class="font-mono font-bold text-white" x-text="voucherDetail.voucher?.username ?? '-'"></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-slate-400">Profile</dt>
                        <dd class="text-right text-sm font-semibold text-white" x-text="voucherDetail.voucher?.hotspot_profile?.profile_name ?? '-'"></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-slate-400">Reseller</dt>
                        <dd class="text-right text-sm font-semibold text-white" x-text="voucherDetail.voucher?.reseller?.full_name ?? '-'"></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-slate-400">Login Pertama</dt>
                        <dd class="text-right text-sm font-semibold text-white" x-text="voucherDetail.voucher?.first_login_at ? new Date(voucherDetail.voucher.first_login_at).toLocaleString('id-ID') : '-'"></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-slate-400">Kadaluarsa</dt>
                        <dd class="text-right text-sm font-semibold text-white" x-text="voucherDetail.voucher?.expires_at ? new Date(voucherDetail.voucher.expires_at).toLocaleString('id-ID') : '-'"></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-slate-400">MAC Terkunci</dt>
                        <dd class="font-mono text-xs font-semibold text-white" x-text="voucherDetail.voucher?.locked_mac ?? '-'"></dd>
                    </div>
                    <div class="flex justify-between border-t border-white/10 pt-3">
                        <dt class="text-sm text-slate-400">Data Terpakai</dt>
                        <dd class="text-right text-sm font-bold text-sky-300" x-text="formatBytes(voucherDetail.voucher?.bytes_used, voucherDetail.voucher?.data_limit_bytes)"></dd>
                    </div>
                    <div x-show="voucherDetail.voucher?.data_limit_bytes" class="flex justify-between">
                        <dt class="text-sm text-slate-400">Limit Data</dt>
                        <dd class="text-right text-sm font-semibold text-white" x-text="formatBytes(voucherDetail.voucher?.data_limit_bytes)"></dd>
                    </div>
                </dl>
            </div>

            <!-- Actions -->
            <div class="mt-5 space-y-3">
                <button
                    x-show="voucherDetail.voucher?.status === 'generated'"
                    @click="activateVoucher(voucherDetail.voucher); voucherDetail.open = false"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-amber-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-amber-500"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.574z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Aktifkan Voucher
                </button>
                <button
                    x-show="voucherDetail.voucher?.status === 'active'"
                    @click="deactivateVoucher(voucherDetail.voucher); voucherDetail.open = false"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-red-500"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    Nonaktifkan Voucher
                </button>
            </div>
        </div>
    </div>
</div>