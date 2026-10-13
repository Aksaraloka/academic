@props(['name', 'solid' => false])
@php
    $file = resource_path('svg/'.($solid ? 'solid' : 'outline')."/{$name}.svg");

    if (! is_file($file)) {
        throw_if(app()->isLocal(), new InvalidArgumentException("Ikon [{$name}] tidak dikenal."));
        $svg = '<svg viewBox="0 0 24 24"></svg>';
    } else {
        $svg = file_get_contents($file);
    }

    $defaultSize = $solid ? 'size-3.5' : 'size-5';
    $attributesHtml = $attributes->merge(['class' => "{$defaultSize} shrink-0", 'aria-hidden' => 'true'])->toHtml();
@endphp
{!! preg_replace('/^<svg/', '<svg '.$attributesHtml, trim($svg), 1) !!}
