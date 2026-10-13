@props(['items'])
<dl {{ $attributes->merge(['class' => 'grid gap-x-6 gap-y-4 sm:grid-cols-2']) }}>
    @foreach ($items as $label => $value)
        <div class="min-w-0">
            <dt class="text-xs font-medium text-text-muted">{{ $label }}</dt>
            <dd class="mt-0.5 break-words text-sm font-medium">{{ filled($value) ? $value : '–' }}</dd>
        </div>
    @endforeach
</dl>
