// Carusel simplu pe derulare nativă (scroll-snap): săgeți, puncte și, la bannere, schimbare automată cât timp
// e pe ecran și nimeni nu interacționează. Cu mișcare redusă: fără schimbare automată, fără animație.
export default function carousel(root) {
    const track = root.querySelector('[data-carousel-track]');
    const slides = [...root.querySelectorAll('[data-carousel-slide]')];
    if (!track || slides.length < 2) return;
    const dots = [...root.querySelectorAll('[data-carousel-dot]')];
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const behavior = reduce ? 'auto' : 'smooth';

    const current = () => {
        const x = track.scrollLeft + track.clientWidth / 2;
        let best = 0;
        slides.forEach((s, i) => { if (Math.abs(s.offsetLeft + s.offsetWidth / 2 - x) < Math.abs(slides[best].offsetLeft + slides[best].offsetWidth / 2 - x)) best = i; });

        return best;
    };
    const go = (i) => {
        const n = (i + slides.length) % slides.length;
        track.scrollTo({ left: slides[n].offsetLeft - track.offsetLeft - (track.clientWidth - slides[n].offsetWidth) / 2 * (dots.length ? 1 : 0), behavior });
    };
    const page = (dir) => {
        if (dots.length) { go(current() + dir); return; }
        track.scrollBy({ left: dir * track.clientWidth * 0.9, behavior });
    };
    root.querySelector('[data-carousel-prev]')?.addEventListener('click', () => { stop(); page(-1); });
    root.querySelector('[data-carousel-next]')?.addEventListener('click', () => { stop(); page(1); });
    dots.forEach((d) => d.addEventListener('click', () => { stop(); go(Number(d.dataset.carouselDot)); }));
    let raf = 0;
    track.addEventListener('scroll', () => {
        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(() => { const c = current(); dots.forEach((d, i) => d.toggleAttribute('aria-current', i === c)); });
    }, { passive: true });

    // schimbare automată (doar bannerele), oprită la prima interacțiune
    let timer = null;
    const delay = Number(root.dataset.carouselAutoplay || 0);
    function stop() { clearInterval(timer); timer = null; root.dataset.stopped = '1'; }
    const start = () => { if (!delay || reduce || timer || root.dataset.stopped) return; timer = setInterval(() => go(current() + 1), delay); };
    const pause = () => { clearInterval(timer); timer = null; };
    if (delay && !reduce) {
        ['pointerdown', 'focusin', 'wheel', 'touchstart'].forEach((ev) => root.addEventListener(ev, stop, { passive: true }));
        root.addEventListener('mouseenter', pause);
        root.addEventListener('mouseleave', start);
        new IntersectionObserver(([e]) => (e.isIntersecting ? start() : pause()), { threshold: 0.4 }).observe(root);
    }
}
