@props(['label', 'name', 'hint' => null, 'required' => false])
<div class="space-y-1.5">
    <label for="{{ $name }}" class="block text-sm font-medium text-text">
        {{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif
    </label>
    {{ $slot }}
    @if ($hint)
        <p class="text-sm text-text-muted">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="text-sm text-danger" role="alert">{{ $message }}</p>
    @enderror
</div>
