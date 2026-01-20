@props([
    'route' => null,
    'click' => null,
    'on' => false,

    // new:
    'align' => 'center',   // center | start | between
    'size' => 'md',        // md | lg | tile
    'indicator' => false,  // show the little cyan dot on the far right
])

@php
    $computed = [];

    if (!is_null($route)) {
        $computed['href'] = route($route);
    }

    if (!is_null($click)) {
        $computed['wire:click'] = $click;
    }

    $sizeClasses = match ($size) {
        // big “card/tile” rows (like your profile selector)
        'tile' => 'min-h-20 px-6 py-4',

        // slightly larger than normal
        'lg'   => 'min-h-16 px-6 py-3',

        // normal button sizing
        default => 'min-h-11 px-5 py-3 sm:py-2',
    };

    $alignClasses = match ($align) {
        'start'   => 'justify-start text-left',
        'between' => 'justify-between',
        default   => 'justify-center text-center',
    };

    $base =
        'hud-btn group relative w-full sm:w-auto overflow-hidden rounded-2xl text-white/90 ' .
        'active:translate-y-px focus:outline-none focus-visible:ring-2 cursor-pointer focus-visible:ring-amber-300/60 ' .
        'flex items-center';
@endphp

<flux:button
    type="button"
    aria-pressed="{{ $on ? 'true' : 'false' }}"
    {{ $attributes->merge($computed)->class([$base, $sizeClasses]) }}
>
    <span class="hud-scan" aria-hidden="true"></span>

    {{-- IMPORTANT: div wrapper (not span) so the slot can contain any layout --}}
    <div class="relative z-10 flex w-full items-center {{ $alignClasses }}">
        {{ $slot }}
    </div>

    @if ($indicator)
        <span class="relative z-10 ml-4 inline-block h-2 w-2 shrink-0 rounded-full bg-cyan-300/70 shadow-[0_0_12px_rgba(34,211,238,.65)]"></span>
    @endif
</flux:button>
