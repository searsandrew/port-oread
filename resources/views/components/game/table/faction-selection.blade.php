{{-- Faction Selection --}}
@if($isSelectingFaction)
    <div class="fixed inset-0 z-[100] bg-zinc-950 text-white">
        <div class="h-full flex flex-col">

            {{-- Header (stays visible on mobile) --}}
            <div class="sticky top-0 z-10 bg-zinc-950/85 backdrop-blur border-b border-white/5">
                <div class="px-5 py-5 text-center">
                    <div class="text-[10px] text-zinc-400 uppercase tracking-[0.35em]">
                        Tactical Deployment
                    </div>
                    <div class="mt-2 text-3xl font-bold tracking-tighter italic">
                        PORT OREAD
                    </div>
                    <div class="mt-2 text-xs text-zinc-400">
                        Pick a deck to deploy. <span class="md:hidden">Swipe to browse.</span> Tap to select.
                    </div>
                </div>
            </div>

            {{-- Body --}}
            <div class="flex-1 flex items-center justify-center">
                <div class="w-full max-w-5xl">

                    {{-- Mobile: horizontal snap carousel | Desktop: 3-col grid --}}
                    <div
                        class="mt-6 md:mt-10
                               flex md:grid md:grid-cols-3
                               gap-5 md:gap-8
                               overflow-x-auto md:overflow-visible
                               snap-x snap-mandatory md:snap-none
                               px-5 md:px-0 pb-6"
                        style="-webkit-overflow-scrolling: touch;"
                    >
                        @foreach(['neupert', 'wami', 'rogers'] as $faction)
                            <button
                                wire:click="selectFaction('{{ $faction }}')"
                                class="group relative
                                       shrink-0 md:shrink
                                       w-[17rem] sm:w-[19rem] md:w-auto
                                       snap-center
                                       flex flex-col items-center
                                       p-6 md:p-8
                                       rounded-[2rem]
                                       border border-white/10
                                       bg-white/5 hover:bg-white/10
                                       transition-all
                                       active:scale-[0.99]"
                            >
                                <div class="w-44 sm:w-48 aspect-[3/4] mb-5 rounded-2xl overflow-hidden border border-white/15 shadow-2xl">
                                    <img
                                        src="{{ asset('/images/cards/' . $faction . '/1.png') }}"
                                        class="w-full h-full object-cover grayscale-[0.35] group-hover:grayscale-0 transition-all duration-500"
                                        alt="{{ ucfirst($faction) }}"
                                    >
                                </div>

                                <h2 class="text-2xl font-black uppercase tracking-tighter italic">
                                    {{ $faction }}
                                </h2>

                                <div class="mt-2 h-1 w-10 bg-white/15 group-hover:w-16 group-hover:bg-white/60 transition-all duration-500"></div>

                                <div class="pointer-events-none absolute inset-0 rounded-[2rem] ring-1 ring-white/0 group-hover:ring-white/10 transition"></div>
                            </button>
                        @endforeach
                    </div>

                    {{-- Footer hint --}}
                    <div class="px-5 md:px-0 pb-8 text-center">
                        <p class="text-[10px] text-zinc-500 uppercase tracking-widest animate-pulse">
                            Awaiting faction authorization...
                        </p>
                    </div>

                </div>
            </div>

        </div>
    </div>
@endif
