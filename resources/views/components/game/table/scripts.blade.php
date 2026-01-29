@once
        <style>
            /* --- Planet HUD (CSS-only) --- */
            @keyframes planetGlow {
                0%, 100% { box-shadow: 0 0 0 rgba(245, 158, 11, 0), 0 0 0 rgba(79,116,146,0); }
                50%      { box-shadow: 0 0 22px rgba(79,116,146,.55), 0 0 14px rgba(245,158,11,.18); }
            }

            @keyframes planetScan {
                0%   { background-position: 0 0, 0 0; }
                100% { background-position: 0 240px, 0 0; }
            }

            @keyframes planetFloat {
                0%,100% { transform: translateY(0); }
                50%     { transform: translateY(-4px); }
            }

            .planet-node {
                animation: planetFloat 7s ease-in-out infinite;
            }

            .planet-orb {
                background: radial-gradient(circle at 30% 30%, rgba(79,116,146,.18), rgba(11,36,66,.30) 55%, rgba(0,0,0,0) 75%);
                filter: drop-shadow(0 12px 28px rgba(0,0,0,.55));
            }

            .planet-aura {
                border: 1px solid rgba(255,255,255,.10);
                background:
                    radial-gradient(circle at 20% 20%, rgba(79,116,146,.30), rgba(11,36,66,.22) 45%, rgba(0,0,0,0) 70%),
                    radial-gradient(circle at 70% 75%, rgba(245,158,11,.10), rgba(0,0,0,0) 60%);
                animation: planetGlow 3.6s ease-in-out infinite;
                overflow: hidden;
            }

            .planet-aura::before {
                content: "";
                position: absolute;
                inset: 0;
                background:
                    repeating-linear-gradient(
                        to bottom,
                        rgba(255,255,255,.08) 0px,
                        rgba(255,255,255,.08) 1px,
                        rgba(0,0,0,0) 3px,
                        rgba(0,0,0,0) 6px
                    );
                opacity: .12;
                mix-blend-mode: overlay;
                animation: planetScan 4.25s linear infinite;
                pointer-events: none;
            }

            .planet-aura::after {
                content: "";
                position: absolute;
                inset: -2px;
                border-radius: 9999px;
                border: 2px solid rgba(79,116,146,.25);
                opacity: .5;
                pointer-events: none;
            }

            .planet-title {
                text-shadow: 0 1px 10px rgba(0,0,0,.70);
            }

            .planet-node:focus-visible {
                outline: 2px solid rgba(245,158,11,.55);
                outline-offset: 4px;
                border-radius: 9999px;
            }
        </style>

        <script>
            function portOreadTable() {
                return {
                    handSwiper: null,
                    planetSwiper: null,
                    handIndex: 0,
                    activeArea: 'hand',

                    // used for win/loss planet nudge
                    planetNudge: null,

                    init() {
                        this.ensureBattleStore();
                        this.initHandSwiper();
                        this.initPlanetSwiper();
                        this.lastPlanetCount = this.planetSwiper?.slides?.length ?? 0;
                    },

                    nudgePlanet(move) {
                        if (!move || move === 'none') return;
                        this.planetNudge = move;
                        setTimeout(() => this.planetNudge = null, 350);
                    },

                    getShatterStyle(i) {
                        const rows = 4;
                        const cols = 4;
                        const r = Math.floor(i / cols);
                        const c = i % cols;
                        const w = 100 / cols;
                        const h = 100 / rows;
                        const tx = (Math.random() - 0.5) * 500;
                        const ty = (Math.random() - 0.5) * 500;
                        const tr = (Math.random() - 0.5) * 500;
                        return `clip-path: inset(${r * h}% ${(cols - 1 - c) * w}% ${(rows - 1 - r) * h}% ${c * w}%); --tx: ${tx}px; --ty: ${ty}px; --tr: ${tr}deg;`;
                    },

                    ensureBattleStore() {
                        const build = () => ({
                            visible: false,
                            playerImg: '',
                            enemyImg: '',
                            outcome: 'tie',
                            planetMove: 'none',
                            shattered: null,
                            potEscalated: false,
                            message: '',

                            run(effects) {
                                if (!effects || !effects.length) return;
                                const e = effects.find(x => x.type === 'battle_resolve');
                                if (!e) return;

                                this.playerImg = e.player?.img || '';
                                this.enemyImg = e.enemy?.img || '';
                                this.outcome = e.outcome || 'tie';
                                this.planetMove = e.planetMove || 'none';
                                this.shattered = null;
                                this.potEscalated = (this.outcome === 'tie');
                                this.message = this.pickMessage(this.outcome, e.player?.strength || 0, e.enemy?.strength || 0);

                                // SINGLE PLAYER: stay up until user accepts
                                this.visible = true;

                                if (this.outcome === 'win') {
                                    setTimeout(() => this.shattered = 'enemy', 600);
                                } else if (this.outcome === 'loss') {
                                    setTimeout(() => this.shattered = 'player', 600);
                                }
                            },

                            pickMessage(outcome, pStr, eStr) {
                                if (outcome === 'tie') return "The conflict escalates. Pot VP increased!";

                                const diff = Math.abs(pStr - eStr);
                                const tier = diff >= 8 ? 'crushing' : (diff >= 3 ? 'solid' : 'narrow');

                                const pools = {
                                    win: {
                                        crushing: [
                                            "Total annihilation. The planet is yours.",
                                            "The enemy was vaporized. Sector claimed.",
                                            "Crushing dominance. Resistance was futile.",
                                            "Absolute conquest achieved. A glorious day.",
                                            "The enemy fled in terror. You claimed the planet."
                                        ],
                                        solid: [
                                            "You claimed the planet.",
                                            "Victory is yours! The planet is secured.",
                                            "The sector falls under your control.",
                                            "Resistance has been crushed.",
                                            "Strategic victory. Sector secured."
                                        ],
                                        narrow: [
                                            "A hard-fought victory. The planet is yours.",
                                            "Narrowly secured the sector.",
                                            "The enemy retreats, but barely.",
                                            "A foothold established, by the skin of your teeth.",
                                            "Victory, though at a cost."
                                        ]
                                    },
                                    loss: {
                                        crushing: [
                                            "Our forces were annihilated. The planet is lost.",
                                            "A humiliating rout. The enemy reigns supreme.",
                                            "The enemy had ruthlessly occupied the planet, leaving nothing behind.",
                                            "Complete catastrophic failure. Sector lost.",
                                            "We were overwhelmed. The enemy holds the world."
                                        ],
                                        solid: [
                                            "The enemy had ruthlessly occupied the planet.",
                                            "Our forces were repelled.",
                                            "Sector lost to enemy control.",
                                            "The enemy's grip on this world tightens.",
                                            "Withdrawal confirmed. The planet is lost."
                                        ],
                                        narrow: [
                                            "A bitter defeat. We almost had them.",
                                            "The enemy held their ground by a narrow margin.",
                                            "Forced to retreat. So close, yet so far.",
                                            "The sector remains contested, but out of our hands.",
                                            "They held on by a thread."
                                        ]
                                    }
                                };

                                const pool = pools[outcome][tier];
                                return pool[Math.floor(Math.random() * pool.length)];
                            },

                            accept() {
                                const move = this.planetMove || 'none';
                                const escalated = this.potEscalated;
                                this.visible = false;
                                this.planetMove = 'none';
                                this.shattered = null;
                                this.potEscalated = false;

                                window.dispatchEvent(new CustomEvent('battle-accepted', {
                                    detail: {planetMove: move, potEscalated: escalated}
                                }));
                            }
                        });

                        const setStore = () => {
                            if (!window.Alpine || !window.Alpine.store) return;
                            if (!window.Alpine.store('battle')) {
                                window.Alpine.store('battle', build());
                            }
                        };

                        setStore();
                        document.addEventListener('alpine:init', setStore);
                    },

                    initHandSwiper() {
                        if (!window.Swiper) return;

                        this.handSwiper = new Swiper(this.$refs.handSwiper, {
                            slidesPerView: 1.35,
                            centeredSlides: true,
                            spaceBetween: 14,
                            // IMPORTANT: we handle click ourselves, so Swiper doesn't auto-slide then we open modal
                            slideToClickedSlide: false,
                        });

                        this.handSwiper.on('slideChange', () => {
                            this.handIndex = this.handSwiper.activeIndex;
                            this.syncSelectedToActiveSlide();
                        });

                        // ✅ Click behavior:
                        // - click peeker => center it
                        // - click active => open modal
                        this.handSwiper.on('click', (swiper) => {
                            this.activeArea = 'hand';
                            const idx = swiper.clickedIndex;
                            if (idx === null || idx === undefined) return;

                            const slide = swiper.slides[idx];
                            const cardId = slide?.dataset?.cardId;
                            if (!cardId) return;

                            const active = swiper.activeIndex;

                            // peeker click centers only
                            if (idx !== active) {
                                this.handIndex = idx;
                                swiper.slideTo(idx);
                                return;
                            }

                            // active click opens modal
                            this.handIndex = active;
                            this.$wire.openCardMenu(cardId);
                        });

                        this.handIndex = this.handSwiper.activeIndex || 0;
                        this.syncSelectedToActiveSlide();
                    },

                    initPlanetSwiper() {
                        if (!window.Swiper) return;

                        this.planetSwiper = new Swiper(this.$refs.planetSwiper, {
                            slidesPerView: 1,
                            centeredSlides: true,
                            spaceBetween: 10,
                            grabCursor: true,
                            simulateTouch: true,
                            keyboard: {enabled: true},
                            mousewheel: {forceToAxis: true},

                            // clickable dots
                            pagination: {
                                el: this.$refs.planetPagination,
                                clickable: true,
                            },
                        });

                        
                        this.lastPlanetCount = this.planetSwiper?.slides?.length ?? 0;
this.planetSwiper.on('click', () => {
                            this.activeArea = 'planets';
                        });
                    },

                    refreshHandSwiper() {
                        this.$nextTick(() => {
                            const idx = this.handIndex;

                            if (this.handSwiper) {
                                this.handSwiper.update();
                                this.handSwiper.slideTo(Math.min(idx, this.handSwiper.slides.length - 1), 0);
                            } else {
                                this.initHandSwiper();
                            }

                            this.syncSelectedToActiveSlide();
                        });
                    },

                    refreshPlanetSwiper() {
                        this.$nextTick(() => {
                            if (!this.planetSwiper) {
                                this.initPlanetSwiper();
                        this.lastPlanetCount = this.planetSwiper?.slides?.length ?? 0;
                                this.lastPlanetCount = this.planetSwiper?.slides?.length ?? 0;
                                return;
                            }

                            const prevCount = this.lastPlanetCount ?? 0;

                            this.planetSwiper.update();

                            const count = this.planetSwiper.slides.length;
                            const max = Math.max(0, count - 1);

                            // If a new planet was added to the pot (tie escalation),
                            // snap to the newest planet (last slide).
                            if (count > prevCount) {
                                this.planetIndex = max;
                                this.planetSwiper.slideTo(max, 0);
                            } else {
                                const current = this.planetSwiper.activeIndex ?? 0;
                                const clamped = Math.min(current, max);
                                this.planetIndex = clamped;
                                this.planetSwiper.slideTo(clamped, 0);
                            }

                            this.lastPlanetCount = count;
                        });
                    },

                    restoreHandIndexSoon() {
                        this.$nextTick(() => {
                            if (!this.handSwiper) return;
                            this.handSwiper.update();
                            this.handSwiper.slideTo(Math.min(this.handIndex, this.handSwiper.slides.length - 1), 0);
                        });
                    },

                    // kept for now (no longer used by slides)
                    handleHandClick(index, cardId) {
                        if (!this.handSwiper) {
                            this.$wire.openCardMenu(cardId);
                            return;
                        }

                        const active = this.handSwiper.activeIndex;

                        if (index !== active) {
                            this.handIndex = index;
                            this.handSwiper.slideTo(index);
                            return;
                        }

                        this.$wire.openCardMenu(cardId);
                    },

                    syncSelectedToActiveSlide() {
                        if (!this.handSwiper) return;
                        const slide = this.handSwiper.slides[this.handSwiper.activeIndex];
                        const id = slide?.dataset?.cardId;
                        if (id) this.$wire.selectedCardId = id;
                    }
                };
            }
        </script>
    @endonce
