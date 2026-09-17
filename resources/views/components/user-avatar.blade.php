@props([
    'user' => null,
    'url' => null,
    'initials' => null,
    'size' => 'h-10 w-10',
    'text' => 'text-sm',
])

@php
    $photo = $url ?? $user?->avatar_url;
    $label = $initials ?? $user?->avatar_initials ?? '';
@endphp

<span {{ $attributes->class([
    'grid shrink-0 place-items-center rounded-full bg-cover bg-center font-semibold',
    $size,
    $text,
    'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-200' => ! $photo,
]) }} @if ($photo) style="background-image:url('{{ $photo }}')" @endif>
    @unless ($photo){{ $label }}@endunless
</span>
