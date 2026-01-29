{{-- HUD --}}
    <div class="px-4 pt-4 shrink-0 transition-opacity duration-300" >
        <div class="flex items-center justify-between mb-4">
            @if($user)
                <div class="flex items-center gap-3">
                    <div
                        class="h-8 w-8 rounded-full bg-zinc-800 border border-white/10 flex items-center justify-center overflow-hidden">
                        <span class="text-[10px] font-bold">{{ substr($user['name'] ?? $user['email'], 0, 1) }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span
                            class="text-[10px] font-bold uppercase tracking-widest text-zinc-100">{{ $user['name'] ?? 'Commander' }}</span>
                        <span class="text-[8px] text-zinc-500 uppercase tracking-tighter">{{ count($availablePlanets) }} Planets Synced</span>
                    </div>
                </div>
            @else
                <div class="flex items-center gap-2">
                    <flux:link :href="route('login')"
                               class="text-[10px] uppercase tracking-widest text-zinc-500 hover:text-white transition-colors">
                        Login to Sync
                    </flux:link>
                </div>
            @endif

            <div class="flex items-center gap-4">
                <div class="h-1.5 w-1.5 rounded-full {{ $user ? 'bg-emerald-500 animate-pulse' : 'bg-zinc-700' }}"
                     title="{{ $user ? 'Online' : 'Offline' }}"></div>
                <button wire:click="sync" class="text-zinc-500 hover:text-white transition-colors"
                        wire:loading.class="animate-spin">
                    <flux:icon.arrow-path class="size-4"/>
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <div class="text-xs text-zinc-400">Score</div>
                <div class="text-xl font-semibold">{{ $hud['score'] ?? 0 }}</div>
            </div>

            <div class="space-y-1 text-right">
                <div class="text-xs text-zinc-400">Pot VP</div>
                <div
                    class="text-xl font-semibold transition-all duration-500"
                    :class="$store.battle?.potEscalated ? 'pot-escalate text-amber-400' : ''"
                >
                    {{ $hud['pot_vp'] ?? 0 }}
                </div>
            </div>
        </div>
    </div>
