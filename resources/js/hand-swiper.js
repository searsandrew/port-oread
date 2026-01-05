import Swiper from "swiper";
import { Navigation } from "swiper/modules";

import "swiper/css";

function getCenteredCardId(swiper) {
    const slide = swiper.slides[swiper.activeIndex];
    if (!slide) return null;
    const img = slide.querySelector("[data-card-id]");
    return img?.getAttribute("data-card-id") ?? null;
}

export function initHandSwiper(el, { onCenterChanged, onSwipeUpPlay } = {}) {
    if (!el) return;

    if (el.__handSwiper) {
        el.__handSwiper.destroy(true, true);
        el.__handSwiper = null;
    }

    const swiper = new Swiper(el, {
        modules: [Navigation],
        slidesPerView: "auto",
        centeredSlides: true,
        slideToClickedSlide: true,
        spaceBetween: 14,
        speed: 220,
        threshold: 6,
        // IMPORTANT: snap, not free drag
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

    // Vertical swipe-up-to-play gesture (only on the active/center slide)
    let startX = 0;
    let startY = 0;
    let tracking = false;

    const SWIPE_UP_PX = 55;     // how far up counts as “play”
    const DOMINANCE = 1.25;     // y must dominate x by this ratio

    el.addEventListener("pointerdown", (e) => {
        // only start if pointerdown happened inside the active slide
        const active = swiper.slides[swiper.activeIndex];
        if (!active || !active.contains(e.target)) return;

        tracking = true;
        startX = e.clientX;
        startY = e.clientY;
    });

    el.addEventListener("pointermove", (e) => {
        if (!tracking) return;
        // don’t prevent default here; we’ll decide on pointerup
    });

    el.addEventListener("pointerup", (e) => {
        if (!tracking) return;
        tracking = false;

        const dx = e.clientX - startX;
        const dy = e.clientY - startY;

        // Swipe up means dy is negative and large in magnitude.
        const up = dy < -SWIPE_UP_PX;
        const verticalDominant = Math.abs(dy) > Math.abs(dx) * DOMINANCE;

        if (up && verticalDominant) {
            onSwipeUpPlay?.();
        }
    });

    el.__handSwiper = swiper;
    return swiper;
}
