<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-sm shadow-slate-950/5 dark:border-white/10 dark:bg-white/[0.04] dark:shadow-black/20']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            {{ $slot }}
        </table>
    </div>
</div>
