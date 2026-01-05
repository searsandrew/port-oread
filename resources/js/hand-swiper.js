// resources/js/hand-swiper.js
import Swiper from "swiper";
import "swiper/css";

/**
 * Get the card id for the currently centered slide.
 * We read it from a data attribute on an element inside the slide.
 */
function getCenteredCardId(swiper) {
    const slide = swiper.slides?.[swiper.activeIndex];
    if (!slide) return null;

    const el = slide.querySelector("[data-card-id]");
    return el?.getAttribute("data-card-id") ?? null;
}

/**
 * Initialize the Hand swiper.
 *
 * Expected markup:
 * <div class="swiper">
 *   <div class="swiper-wrapper">
 *     <div class="swiper-slide">
 *        <img data-card-id="c1" ...>
 *     </div>
 *   </div>
 * </div>
 */
export function initHandSwiper(el, { onCenterChanged, onSwipeUpPlay } = {}) {
    if (!el) return;

    // If already initialized, destroy first.
    if (el.__handSwiper) {
        el.__handSwiper.destroy(true, true);
        el.__handSwiper = null;
    }

    const swiper = new Swiper(el, {
        slidesPerView: "auto",
        centeredSlides: true,

        // Clicking a "peek" card moves it to center
        slideToClickedSlide: true,

        // spacing between cards (peeks)
        spaceBetween: 14,

        // snap feel
        speed: 220,
        threshold: 6,

        // IMPORTANT: snap stepping, not free dragging
        freeMode: false,

        resistanceRatio: 0.85,

        on: {
            init(s) {
                onCenterChanged?.(getCenteredCardId(s));
            },
            slideChange(s) {
                onCenterChanged?.(getCenteredCardId(s));
            },
        },
    });

    // --- Swipe-up-to-play gesture ---
    // Only triggers if the gesture starts on the centered (active) slide.
    let startX = 0;
    let startY = 0;
    let tracking = false;

    const SWIPE_UP_PX = 55; // how far up counts as “play”
    const DOMINANCE = 1.25; // y distance must dominate x distance

    el.addEventListener("pointerdown", (e) => {
        const active = swiper.slides?.[swiper.activeIndex];
        if (!active || !active.contains(e.target)) return;

        tracking = true;
        startX = e.clientX;
        startY = e.clientY;
    });

    el.addEventListener("pointerup", (e) => {
        if (!tracking) return;
        tracking = false;

        const dx = e.clientX - startX;
        const dy = e.clientY - startY;

        const isSwipeUp = dy < -SWIPE_UP_PX;
        const isVerticalDominant = Math.abs(dy) > Math.abs(dx) * DOMINANCE;

        if (isSwipeUp && isVerticalDominant) {
            onSwipeUpPlay?.();
        }
    });

    el.__handSwiper = swiper;
    return swiper;
}
