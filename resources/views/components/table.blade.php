<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-[1.5rem] border border-emerald-950/10 bg-white/85 shadow-sm']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            {{ $slot }}
        </table>
    </div>
</div>
