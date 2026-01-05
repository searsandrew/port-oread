import Swiper from "swiper";
import "swiper/css";

export function initPlanetSwiper(el) {
    if (!el) return;

    if (el.__planetSwiper) {
        el.__planetSwiper.destroy(true, true);
        el.__planetSwiper = null;
    }

    const swiper = new Swiper(el, {
        slidesPerView: "auto",
        centeredSlides: true,
        slideToClickedSlide: true,
        spaceBetween: 14,
        speed: 220,
        threshold: 6,
    });

    el.__planetSwiper = swiper;
    return swiper;
}
