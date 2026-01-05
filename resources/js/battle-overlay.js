// resources/js/battle-overlay.js

function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function nextFrame() {
    return new Promise((resolve) => requestAnimationFrame(() => resolve()));
}

function el(tag, className, attrs = {}) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    for (const [k, v] of Object.entries(attrs)) node.setAttribute(k, v);
    return node;
}

function resolveImg(effectSide) {
    // Prefer explicit image path from engine:
    if (effectSide?.img) return effectSide.img;

    // Optional fallback if you later send IDs:
    if (effectSide?.id) return `/images/cards/${effectSide.id}.png`;

    return null;
}

export function battleOverlay() {
    return {
        running: false,
        queue: [],

        run(effects = []) {
            if (!Array.isArray(effects) || effects.length === 0) return;

            // enqueue all, process sequentially
            this.queue.push(...effects);
            if (!this.running) this._drain();
        },

        async _drain() {
            this.running = true;

            while (this.queue.length) {
                const fx = this.queue.shift();

                if (fx?.type === "battle_resolve") {
                    await this._battleResolve(fx);
                }
            }

            this.running = false;
        },

        async _battleResolve(fx) {
            // fx contract (recommended):
            // {
            //   type: 'battle_resolve',
            //   player: { img: '/path/to/player.png' },  // (or { id: 'c123' })
            //   enemy:  { img: '/path/to/enemy.png' },
            //   outcome: 'win'|'loss'|'tie',
            //   planetMove: 'down'|'up'|'none'          // optional; derived from outcome if omitted
            // }

            const outcome = fx.outcome ?? "tie";
            const planetMove = fx.planetMove ?? (outcome === "win" ? "down" : outcome === "loss" ? "up" : "none");

            // Clean overlay
            this.$el.innerHTML = "";

            const playerImg = resolveImg(fx.player);
            const enemyImg = resolveImg(fx.enemy);

            // If we can’t resolve images, no-op gracefully.
            if (!playerImg || !enemyImg) return;

            // Build two “floating card” nodes
            // Using absolute + transforms so it feels native.
            const playerCard = el(
                "div",
                "absolute left-1/2 bottom-[18%] -translate-x-1/2 w-[60%] max-w-[280px] opacity-0",
            );
            const enemyCard = el(
                "div",
                "absolute left-1/2 top-[12%] -translate-x-1/2 w-[60%] max-w-[280px] opacity-0",
            );

            // Card shells
            const playerShell = el(
                "div",
                "rounded-2xl overflow-hidden border border-zinc-700 bg-zinc-900 shadow-xl"
            );
            const enemyShell = el(
                "div",
                "rounded-2xl overflow-hidden border border-zinc-700 bg-zinc-900 shadow-xl"
            );

            const pImg = el("img", "block w-full h-auto select-none", { src: playerImg, draggable: "false" });
            const eImg = el("img", "block w-full h-auto select-none", { src: enemyImg, draggable: "false" });

            playerShell.appendChild(pImg);
            enemyShell.appendChild(eImg);
            playerCard.appendChild(playerShell);
            enemyCard.appendChild(enemyShell);

            // Start transforms (off-center a bit, scaled down)
            playerCard.style.transform = "translateX(-50%) translateY(70px) scale(0.92)";
            enemyCard.style.transform = "translateX(-50%) translateY(-70px) scale(0.92)";

            // Transition setup
            const transition = "transform 260ms ease, opacity 220ms ease, filter 180ms ease, box-shadow 180ms ease";
            playerCard.style.transition = transition;
            enemyCard.style.transition = transition;

            this.$el.appendChild(enemyCard);
            this.$el.appendChild(playerCard);

            // Animate IN
            await nextFrame();
            enemyCard.style.opacity = "1";
            playerCard.style.opacity = "1";
            enemyCard.style.transform = "translateX(-50%) translateY(0px) scale(1)";
            playerCard.style.transform = "translateX(-50%) translateY(0px) scale(1)";

            await wait(280);

            // Winner highlight
            const glow = "drop-shadow(0 0 18px rgba(255,255,255,0.18))";
            const winRing = "0 0 0 3px rgba(255,255,255,0.20), 0 12px 40px rgba(0,0,0,0.35)";

            if (outcome === "win") {
                playerShell.style.boxShadow = winRing;
                playerShell.style.filter = glow;
                enemyShell.style.filter = "grayscale(0.35)";
            } else if (outcome === "loss") {
                enemyShell.style.boxShadow = winRing;
                enemyShell.style.filter = glow;
                playerShell.style.filter = "grayscale(0.35)";
            } else {
                // tie pulse: both briefly glow
                playerShell.style.filter = glow;
                enemyShell.style.filter = glow;
            }

            await wait(420);

            // Planet movement (happens during “discard out” so it feels snappy)
            this._animatePlanetMove(planetMove);

            // Animate OUT (discard)
            // Player discards down-left, enemy discards up-right
            playerCard.style.opacity = "0";
            enemyCard.style.opacity = "0";
            playerCard.style.transform = "translateX(-50%) translate(-90px, 140px) rotate(-6deg) scale(0.90)";
            enemyCard.style.transform = "translateX(-50%) translate(90px, -140px) rotate(6deg) scale(0.90)";

            await wait(280);

            // Cleanup overlay
            this.$el.innerHTML = "";

            // If tie, give a little planet “tie pulse” so the new planet carousel feels intentional
            if (outcome === "tie") {
                this._pulsePlanetTie();
                // You will already be updating planets via snapshot + `planets-updated`,
                // so the swiper becomes active naturally.
            }
        },

        _animatePlanetMove(direction) {
            const stage = document.getElementById("planet-stage");
            if (!stage) return;

            // Reset first
            stage.style.transition = "transform 280ms ease";
            stage.style.transform = "translateY(0px)";

            // Move only if win/loss
            if (direction === "down") {
                stage.style.transform = "translateY(24px)";
                setTimeout(() => (stage.style.transform = "translateY(0px)"), 180);
            } else if (direction === "up") {
                stage.style.transform = "translateY(-24px)";
                setTimeout(() => (stage.style.transform = "translateY(0px)"), 180);
            }
        },

        _pulsePlanetTie() {
            const stage = document.getElementById("planet-stage");
            if (!stage) return;

            stage.style.transition = "transform 120ms ease";
            stage.style.transform = "scale(0.99)";
            setTimeout(() => (stage.style.transform = "scale(1.01)"), 120);
            setTimeout(() => (stage.style.transform = "scale(1)"), 240);
        },
    };
}
