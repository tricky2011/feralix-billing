@props(['status' => null])

<span
    {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide']) }}
    data-status="{{ $status }}"
>
    {{ $slot }}
</span>
