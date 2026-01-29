{{-- Battle Overlay (Accept-based, blocks input) --}}
    <div
        wire:ignore
        class="fixed inset-0 z-60"
        x-cloak
        x-show="$store.battle && $store.battle.visible"
        x-on:game-effects.window="$store.battle && $store.battle.run($event.detail.effects)"
    >
        <div class="absolute inset-0 bg-black/80"></div>

        <div class="absolute inset-0 grid place-items-center">
            <div class="w-full max-w-md px-6">
                <div class="relative h-80 w-full flex items-center justify-center">
                    {{-- Player Card --}}
                    <div
                        class="absolute w-40 aspect-[3/4] transition-all duration-1000"
                        :class="$store.battle.shattered === 'enemy' ? 'z-30 scale-125 translate-x-0' : ($store.battle.shattered === 'player' ? 'opacity-0 scale-50 -translate-x-24' : '-translate-x-24')"
                    >
                        <div class="shatter-container rounded-2xl overflow-hidden"
                             :class="{ 'winner-highlight': $store.battle.outcome === 'win', 'shattered': $store.battle.shattered === 'player' }">
                            <template x-for="i in 16">
                                <div class="shatter-piece"
                                     :style="getShatterStyle(i-1) + '; background-image: url(' + $store.battle.playerImg + ');'"></div>
                            </template>
                        </div>
                        <div x-show="$store.battle.outcome === 'win'"
                             class="absolute -top-4 -left-4 bg-white text-black px-2 py-1 text-[10px] font-bold uppercase rounded z-20">
                            Winner
                        </div>
                    </div>

                    {{-- Enemy Card --}}
                    <div
                        class="absolute w-40 aspect-[3/4] transition-all duration-1000"
                        :class="$store.battle.shattered === 'player' ? 'z-30 scale-125 translate-x-0' : ($store.battle.shattered === 'enemy' ? 'opacity-0 scale-50 translate-x-24' : 'translate-x-24')"
                    >
                        <div class="shatter-container rounded-2xl overflow-hidden"
                             :class="{ 'winner-highlight': $store.battle.outcome === 'loss', 'shattered': $store.battle.shattered === 'enemy' }">
                            <template x-for="i in 16">
                                <div class="shatter-piece"
                                     :style="getShatterStyle(i-1) + '; background-image: url(' + $store.battle.enemyImg + ');'"></div>
                            </template>
                        </div>
                        <div x-show="$store.battle.outcome === 'loss'"
                             class="absolute -top-4 -right-4 bg-white text-black px-2 py-1 text-[10px] font-bold uppercase rounded z-20">
                            Winner
                        </div>
                    </div>
                </div>

                <div class="mt-12 text-center">
                    <div x-show="$store.battle.outcome === 'win'" class="text-2xl font-bold text-white tracking-tight">
                        VICTORY
                    </div>
                    <div x-show="$store.battle.outcome === 'loss'"
                         class="text-2xl font-bold text-zinc-500 tracking-tight">DEFEAT
                    </div>
                    <div x-show="$store.battle.outcome === 'tie'"
                         class="text-2xl font-bold text-amber-400 tracking-tight animate-bounce">TIE!
                    </div>

                    <div class="mt-2 text-sm text-zinc-400 h-10 flex items-center justify-center">
                        <span x-text="$store.battle.message"></span>
                    </div>
                </div>

                <div class="mt-8 flex justify-center">
                    <button
                        type="button"
                        class="group relative px-8 py-3 bg-white text-black font-bold rounded-full overflow-hidden transition-all hover:scale-105 active:scale-95"
                        x-on:click="$store.battle.accept()"
                    >
                        <span class="relative z-10">CONTINUE</span>
                        <div
                            class="absolute inset-0 bg-zinc-200 translate-y-full group-hover:translate-y-0 transition-transform"></div>
                    </button>
                </div>
            </div>
        </div>
    </div>
