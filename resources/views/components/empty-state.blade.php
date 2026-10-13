@props(['icon' => 'inbox', 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-4 py-10 text-center']) }}>
    <div class="flex size-12 items-center justify-center rounded-full bg-surface-muted text-text-muted">
        <x-icon :name="$icon" class="size-6" />
    </div>
    <p class="mt-3 font-semibold">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-text-muted">{{ $description }}</p>
    @endif
    @if (trim((string) $slot) !== '')
        <div class="mt-4 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
