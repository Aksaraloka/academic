@props(['title', 'subtitle' => null])
<div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
        <h1 class="text-2xl font-bold sm:text-3xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-text-muted">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
