// Prima secțiune: un singur moment orchestrat la încărcare (titlu, text, butoane). Cu mișcare redusă: nimic.
import { gsap } from 'gsap';

export default function heroIntro(root) {
    const mm = gsap.matchMedia();
    mm.add('(prefers-reduced-motion: no-preference)', () => {
        const tl = gsap.timeline({ defaults: { ease: 'power3.out', duration: 0.9 } });
        tl.from(root.querySelector('.hero__title'), { y: 28, opacity: 0 })
            .from(root.querySelector('.lead'), { y: 18, opacity: 0 }, '-=0.6')
            .from(root.querySelectorAll('.hero__actions .btn'), { y: 14, opacity: 0, stagger: 0.08 }, '-=0.6')
            .from(root.querySelector('.hero__b2b'), { opacity: 0 }, '-=0.4');
        const facts = root.closest('.hero')?.querySelectorAll('.hero__facts li');
        if (facts?.length) tl.from(facts, { opacity: 0, y: 10, stagger: 0.08, duration: 0.6 }, '-=0.5');
    });
}
