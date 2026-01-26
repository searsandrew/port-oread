@props([
    'route' => null,
    'click' => null,
    'on' => false,

    'align' => 'center',   // center | start | between
    'size' => 'md',        // md | lg | tile
    'indicator' => false,  // show the little cyan dot on the far right
    'disabled' => false,
])

@php
    $computed = [];

    if (!is_null($route)) {
        $computed['href'] = route($route);
    }

    // If disabled, don't bind clicks and make it inert
    if ($disabled) {
        $computed['disabled'] = 'disabled';
        $computed['aria-disabled'] = 'true';
        $computed['tabindex'] = '-1';
    } else {
        if (!is_null($click)) {
            $computed['wire:click'] = $click;
        }
    }

    $sizeClasses = match ($size) {
        'tile' => 'min-h-20 px-6 py-4',
        'lg'   => 'min-h-16 px-6 py-3',
        default => 'min-h-11 px-5 py-3 sm:py-2',
    };

    $alignClasses = match ($align) {
        'start'   => 'justify-start text-left',
        'between' => 'justify-between',
        default   => 'justify-center text-center',
    };

    $base =
        'hud-btn group relative w-full sm:w-auto overflow-hidden rounded-2xl text-white/90 ' .
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300/60 ' .
        'flex items-center select-none touch-manipulation';

    $state = $disabled
        ? 'cursor-not-allowed'
        : 'cursor-pointer active:translate-y-px';
@endphp

<flux:button
    type="button"
    aria-pressed="{{ $on ? 'true' : 'false' }}"
    {{ $attributes->merge($computed)->class([$base, $state, $sizeClasses]) }}
>
    <span class="hud-scan" aria-hidden="true"></span>

    <div class="relative z-10 flex w-full items-center {{ $alignClasses }}">
        {{ $slot }}
    </div>

    @if ($indicator)
        <span class="relative z-10 ml-4 inline-block h-2 w-2 shrink-0 rounded-full bg-cyan-300/70 shadow-[0_0_12px_rgba(34,211,238,.65)]"></span>
    @endif
</flux:button>
