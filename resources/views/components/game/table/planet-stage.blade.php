{{-- Planet Stage (Swiper) --}}
    {{-- Planet Stage (Swiper) --}}
    <div
        class="px-4 pt-4 shrink-0"
        x-on:click="activeArea = 'planets'"
    >
        <div class="flex items-center justify-between">
            <div class="text-sm text-zinc-400">Planet(s) in the pot</div>

            <div class="flex items-center gap-2">
                {{-- desktop-friendly nav --}}
                <button
                    type="button"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-zinc-200 hover:bg-white/10 transition-colors"
                    x-ref="planetPrev"
                    x-on:click="planetSwiper?.slidePrev()"
                    :disabled="!planetSwiper || planetSwiper.slides.length <= 1"
                >
                    ◀
                </button>
                <button
                    type="button"
                    class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-zinc-200 hover:bg-white/10 transition-colors"
                    x-ref="planetNext"
                    x-on:click="planetSwiper?.slideNext()"
                    :disabled="!planetSwiper || planetSwiper.slides.length <= 1"
                >
                    ▶
                </button>
            </div>
        </div>

        <div class="mt-3">
            <div class="swiper max-w-[22rem] sm:max-w-[26rem] mx-auto" x-ref="planetSwiper">
                <div class="swiper-wrapper">
                    @foreach($planets as $planet)
                        @php
                            $filename = $planet['filename'] ?? null;

                            // Gameplay planet image (round orb)
                            $planetImg = $planet['img'] ?? ($filename ? asset('/images/planets/' . $filename) : null);

                            // Modal uses the *card* art version.
                            $planetCardImg =
                                $planet['card_img']
                                ?? ($filename ? asset('/images/cards/planets/' . $filename) : null)
                                ?? (is_string($planetImg) ? str_replace('/images/planets/', '/images/cards/planets/', $planetImg) : null);

                            $planetName  = $planet['name'] ?? 'Unknown';
                            $planetClass = $planet['class'] ?? $planet['planet_class'] ?? null;
                            $planetType  = $planet['type'] ?? $planet['planet_type'] ?? null;
                            $planetVp    = $planet['vp'] ?? 0;
                        @endphp

                        <div class="swiper-slide" wire:key="planet-{{ $planet['id'] }}-{{ $loop->index }}">
                            <button
                                type="button"
                                class="planet-node group relative block mx-auto w-[18rem] sm:w-[20rem] aspect-square select-none"
                                wire:click="openPlanetModal('{{ $planet['id'] }}')"
                            >
                                {{-- aura + scanline layer --}}
                                <span class="absolute inset-0 rounded-full planet-aura"></span>

                                {{-- planet image --}}
                                @if($planetImg)
                                    <img
                                        src="{{ $planetImg }}"
                                        class="relative z-10 w-full h-full rounded-full object-contain planet-orb"
                                        alt="{{ $planetName }}"
                                        draggable="false"
                                    />
                                @else
                                    <div class="relative z-10 w-full h-full rounded-full grid place-items-center bg-white/5 border border-white/10 text-xs text-zinc-300">
                                        No image
                                    </div>
                                @endif

                                {{-- top left: class --}}
                                @if($planetClass)
                                    <span class="absolute top-3 left-3 z-20 rounded-full bg-zinc-950/70 border border-white/10 px-2.5 py-1 backdrop-blur">
                                    {{-- icon hook (drop in your SVG component later) --}}
                                        {{-- <x-icons.planet-class :value="$planetClass" class="size-3 text-amber-300" /> --}}
                                    <span class="text-[9px] uppercase tracking-widest text-zinc-200">{{ $planetClass }}</span>
                                </span>
                                @endif

                                {{-- top right: type --}}
                                @if($planetType)
                                    <span class="absolute top-3 right-3 z-20 rounded-full bg-zinc-950/70 border border-white/10 px-2.5 py-1 backdrop-blur">
                                    {{-- <x-icons.planet-type :value="$planetType" class="size-3 text-amber-300" /> --}}
                                    <span class="text-[9px] uppercase tracking-widest text-zinc-200">{{ $planetType }}</span>
                                </span>
                                @endif

                                {{-- bottom label --}}
                                <span class="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 w-[86%] text-center">
                                <span class="block planet-title text-white font-black uppercase tracking-wider text-base sm:text-lg leading-tight truncate">
                                    {{ $planetName }}
                                </span>
                                <span class="mt-1 inline-flex items-center justify-center gap-2">
                                    <span class="text-[11px] text-amber-300 font-black tracking-widest">{{ $planetVp }} VP</span>
                                </span>
                            </span>

                                {{-- stash card image for modal --}}
                                <span class="hidden" data-planet-card-img="{{ $planetCardImg }}"></span>
                            </button>
                        </div>
                    @endforeach
                </div>

                {{-- pagination bullets (clickable) --}}
                <div class="mt-2 flex justify-center" x-ref="planetPagination"></div>
            </div>
        </div>

        @if(count($planets) > 1)
            <div class="mt-2 text-xs text-zinc-500">
                Tip: swipe on mobile; on desktop you can use ◀ ▶, bullet dots, or arrow keys.
            </div>
        @endif
    </div>



    {{-- Spacer to push hand to bottom --}}
    <div class="flex-1"></div>
