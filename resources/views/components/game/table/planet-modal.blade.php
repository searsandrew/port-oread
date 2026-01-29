{{-- Planet View Modal --}}
    <div
        x-cloak
        x-show="$wire.showPlanetModal"
        class="fixed inset-0 z-40 flex flex-col justify-end"
    >
        <div
            class="absolute inset-0 bg-black/60"
            x-show="$wire.showPlanetModal"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="$wire.closePlanetModal()"
        ></div>

        <div
            class="relative flex flex-col"
            x-show="$wire.showPlanetModal"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            style="will-change: transform;"
        >
            @if($selectedPlanet)
                <div class="px-4">
                    @php
                        /**
                         * Turn a public path (e.g. "/images/...") into a full URL via asset().
                         * If it is already an absolute URL, leave it alone.
                         */
                        $pathToUrl = function (?string $path): ?string {
                            if (! $path) return null;

                            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                                return $path;
                            }

                            return asset(ltrim($path, '/'));
                        };

                        $spImgUrl = $pathToUrl($selectedPlanet['img'] ?? null);
                        $spCardUrl = $pathToUrl($selectedPlanet['card_img'] ?? null)
                            ?? ($spImgUrl ? str_replace('/images/planets/', '/images/cards/planets/', $spImgUrl) : null);
                    @endphp

                    <img
                        src="{{ $spCardUrl ?? $spImgUrl }}"
                        class="w-full rounded-t-3xl border-t border-x border-white/20 shadow-2xl"
                        alt="{{ $selectedPlanet['name'] ?? 'Planet' }}"
                        draggable="false"
                    />
                </div>

                <div class="bg-zinc-950 border-t border-white/10 safe-area-bottom">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-2xl font-bold">{{ $selectedPlanet['name'] }}</h2>
                                <p class="text-zinc-400 uppercase tracking-widest text-xs mt-1">{{ $selectedPlanet['type'] }}</p>
                            </div>
                            <div
                                class="h-12 w-12 rounded-full border border-amber-400/20 bg-amber-400/10 grid place-items-center text-amber-400 font-black">
                                {{ $selectedPlanet['vp'] }}
                            </div>
                        </div>

                        <p class="mt-4 text-zinc-300 leading-relaxed italic">
                            "{{ $selectedPlanet['flavor'] }}"
                        </p>
                    </div>

                    <div class="border-t border-white/5">
                        <button
                            class="w-full py-6 text-lg font-medium text-zinc-400 hover:bg-white/5 active:bg-white/10 transition-colors"
                            wire:click="closePlanetModal">
                            CLOSE
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>
