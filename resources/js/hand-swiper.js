import Swiper from "swiper";
import { FreeMode } from "swiper/modules";

import "swiper/css";
import "swiper/css/free-mode";

export function initHandSwiper(el) {
    if (!el) return;

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
        freeMode: { enabled: true, momentum: true },
        grabCursor: true,
        threshold: 6,
        on: {
            touchMove() { dragging = true; },
            touchEnd() { setTimeout(() => (dragging = false), 0); },
        },
    });

    el.__handSwiper = swiper;
    el.__isHandDragging = () => dragging;

    return swiper;
}
