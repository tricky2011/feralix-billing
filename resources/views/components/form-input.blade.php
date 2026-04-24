@props([
    'name',
    'label',
    'type' => 'text',
    'placeholder' => '',
])

<label class="block">
    <span class="text-sm font-bold text-slate-700">{{ $label }}</span>
    <input
        name="{{ $name }}"
        type="{{ $type }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' => 'mt-2 w-full rounded-2xl border border-emerald-950/10 bg-white/80 px-4 py-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-emerald-700 focus:bg-white focus:ring-4 focus:ring-emerald-700/10']) }}
    >
</label>
