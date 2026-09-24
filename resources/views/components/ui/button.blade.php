{{-- FMS Global SOFT Button Component — single reusable button API for all modules.
     Renders the global SOFT .btn system (public/css/app.css).
     ALL variants share height 40px / radius 6px / 14px font / 1px border.
     ONLY background / text / border color changes per variant.

     Usage:
         <x-ui.button variant="primary">Save</x-ui.button>
         <x-ui.button variant="success">Approve</x-ui.button>
         <x-ui.button variant="danger" type="submit" onclick="return confirm('Delete?')">Delete</x-ui.button>
         <x-ui.button variant="secondary" href="{{ route('admin.budgets.index') }}">Back</x-ui.button>
         <x-ui.button variant="secondary" size="sm" href="...">View</x-ui.button>
         <x-ui.button variant="primary" block>Login</x-ui.button>

     Props:
         variant: primary | success | secondary | danger (default: primary)
         size:    sm | md | lg (default: md → 40px standard, sm → 34px table)
         href:    when set, renders <a>; otherwise renders <button>
         type:    button type when rendering <button> (default: button)
         block:   full-width button (adds .btn-block)
     All other attributes (id, class, onclick, disabled, target, ...) are forwarded.
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'block' => false,
])
@php
    $variantClass = match ($variant) {
        'secondary' => 'btn-secondary',
        'danger' => 'btn-danger',
        'success' => 'btn-success',
        default => 'btn-primary',
    };
    $sizeClass = match ($size) {
        'sm' => 'btn-sm',
        'lg' => 'btn-lg',
        default => '',
    };
    $classes = trim('btn ' . $variantClass . ' ' . $sizeClass . ($block ? ' btn-block' : ''));
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
