{{-- Game Over --}}
    <div x-cloak x-show="$wire.gameOver" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/80"></div>

        <div class="absolute inset-0 grid place-items-center p-4">
            <div class="w-full max-w-sm rounded-3xl border border-white/10 bg-zinc-950 p-4">
                <div class="text-lg font-semibold">Game Over</div>
                <div class="mt-1 text-sm text-zinc-300">
                    {{ $endReasonLabel($endReason) }}
                </div>

                <div class="mt-4 rounded-2xl border border-white/10 bg-white/5 p-3">
                    <div class="text-xs text-zinc-400">Your VP</div>
                    <div class="text-2xl font-semibold">{{ $hud['score'] ?? 0 }}</div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button class="rounded-2xl bg-white/10 px-3 py-3 text-sm" wire:click="newGame">
                        New Game
                    </button>
                    <button class="rounded-2xl bg-white/5 px-3 py-3 text-sm" x-on:click="$wire.gameOver = false">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
