@props(['title' => null, 'padding' => true])
<div {{ $attributes->merge(['class' => 'rounded-card border border-border bg-surface']) }}>
    @if ($title || isset($actions))
        <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3 sm:px-5">
            <h2 class="text-base font-semibold">{{ $title }}</h2>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div @class(['p-4 sm:p-5' => $padding])>
        {{ $slot }}
    </div>
</div>
