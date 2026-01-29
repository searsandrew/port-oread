{{-- Select / Play Modal --}}
    <div
        x-cloak
        x-show="$wire.showCardMenu"
        class="fixed inset-0 z-40 flex flex-col justify-end"
    >
        <div
            class="absolute inset-0 bg-black/60"
            x-show="$wire.showCardMenu"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="$wire.closeCardMenu()"
        ></div>

        <div
            class="relative flex flex-col"
            x-show="$wire.showCardMenu"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            style="will-change: transform;"
        >
            @php
                $selected = collect($hand)->firstWhere('id', $selectedCardId);
            @endphp

            @if($selected)
                <div class="px-4">
                    <img src="{{ asset($selected['img']) }}"
                         class="w-full rounded-t-3xl border-t border-x border-white/20 shadow-2xl" draggable="false"/>
                </div>

                <div class="bg-zinc-950 border-t border-white/10 safe-area-bottom">
                    @if(($selected['isMerc'] ?? false) === true)
                        <div class="p-4 text-center border-b border-white/5 bg-white/5">
                            <div class="text-[10px] text-zinc-400 uppercase tracking-widest">Mercenary</div>
                            <div class="mt-1 text-sm font-bold">
                                {{ $selected['merc']['name'] ?? 'Mercenary' }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-300 leading-relaxed">
                                <span class="font-bold text-white">{{ $mercAbilityTitle($selected['merc']['ability_type'] ?? null) }}:</span>
                                {!! $mercAbilityDescription($selected['merc']['ability_type'] ?? null, $selected['merc']['params'] ?? []) !!}
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-2">
                        <button class="py-6 text-lg font-bold hover:bg-white/5 active:bg-white/10 transition-colors"
                                wire:click="playSelected">
                            PLAY
                        </button>
                        <button
                            class="py-6 text-lg font-medium text-zinc-400 border-l border-white/10 hover:bg-white/5 active:bg-white/10 transition-colors"
                            wire:click="closeCardMenu">
                            CANCEL
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
