@props(['status'])
<x-badge :variant="$status->variant()" :icon="$status->icon()" {{ $attributes }}>{{ $status->label() }}</x-badge>
