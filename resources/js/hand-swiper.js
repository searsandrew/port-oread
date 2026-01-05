import Swiper from "swiper";
import { FreeMode } from "swiper/modules";

import "swiper/css";
import "swiper/css/free-mode";

/**
 * Creates a hand swiper tied to an element. Safe to call multiple times;
 * it will destroy & re-init if needed.
 */
export function initHandSwiper(el) {
    if (!el) return;

    // If already initialized, destroy first
    if (el.__handSwiper) {
        el.__handSwiper.destroy(true, true);
        el.__handSwiper = null;
    }

    let dragging = false;

    const swiper = new Swiper(el, {
        modules: [FreeMode],
        slidesPerView: "auto",
        spaceBetween: 12,
        centeredSlides: true,
        freeMode: {
            enabled: true,
            sticky: false,
            momentum: true,
        },
        grabCursor: true,
        threshold: 6, // how far before a touch is considered a drag
        on: {
            touchMove() {
                dragging = true;
            },
            touchEnd() {
                // give click a beat to not trigger flip after drag
                setTimeout(() => (dragging = false), 0);
            },
        },
    });

    // ignore click while dragging
    el.__handSwiper = swiper;
    el.__isHandDragging = () => dragging;

    return swiper;
}
