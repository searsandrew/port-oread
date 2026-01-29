{{-- Hand Carousel --}}
    <div
        class="px-4 pb-8 pt-6 shrink-0 transition-all duration-500"
        x-on:click="activeArea = 'hand'"
    >
        <div class="relative">
            <div class="swiper" x-ref="handSwiper">
                <div class="swiper-wrapper">
                    @foreach($hand as $card)
                        <div class="swiper-slide" data-card-id="{{ $card['id'] }}" wire:key="hand-{{ $card['id'] }}">
                            <div class="select-none">
                                <div class="relative">
                                    <img
                                        src="{{ asset($card['img']) }}"
                                        class="block w-full h-auto rounded-2xl border border-white/10"
                                        draggable="false"
                                    />

                                    {{-- merc pip --}}
                                    @if(($card['isMerc'] ?? false) === true)
                                        <div
                                            class="absolute top-2 right-2 h-3 w-3 rounded-full border border-white/30 bg-white/20"></div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div
                class="pointer-events-none absolute inset-y-0 left-0 w-8 bg-gradient-to-r from-zinc-950 to-transparent z-10"></div>
            <div
                class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-zinc-950 to-transparent z-10"></div>
        </div>
    </div>
