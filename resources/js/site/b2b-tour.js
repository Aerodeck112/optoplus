// Turul portalului pentru parteneri: file accesibile (săgeți, Home/End), schimbare automată cât timp
// secțiunea e pe ecran și vizitatorul nu a ales singur o filă; se oprește la prima interacțiune.
const STEP_MS = 7000;

export default function tour(root) {
    const tabs = [...root.querySelectorAll('[role="tab"]')];
    const panels = tabs.map((t) => document.getElementById(t.getAttribute('aria-controls')));
    if (tabs.length < 2) return;
    let current = 0;
    let timer = null;
    let auto = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    root.style.setProperty('--tour-ms', `${STEP_MS}ms`);

    const select = (i, focus = false) => {
        current = (i + tabs.length) % tabs.length;
        tabs.forEach((t, k) => {
            const on = k === current;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.tabIndex = on ? 0 : -1;
            if (on) panels[k].removeAttribute('data-inactive'); else panels[k].setAttribute('data-inactive', '');
        });
        if (focus) tabs[current].focus();
        // pe telefon filele sunt pe un rând orizontal: aducem fila curentă în vedere
        // (doar orizontal, fără să miște pagina)
        const bar = tabs[current].parentElement;
        if (bar.scrollWidth > bar.clientWidth) bar.scrollTo({ left: tabs[current].offsetLeft - bar.offsetLeft - 8, behavior: 'smooth' });
    };
    const stop = () => { auto = false; clearInterval(timer); timer = null; root.classList.remove('is-auto'); };
    const start = () => {
        if (!auto || timer) return;
        root.classList.add('is-auto');
        // reluăm animația barei de progres pe fila curentă
        select(current);
        timer = setInterval(() => select(current + 1), STEP_MS);
    };
    const pause = () => { clearInterval(timer); timer = null; root.classList.remove('is-auto'); };

    tabs.forEach((t, i) => {
        t.addEventListener('click', () => { stop(); select(i); });
        t.addEventListener('keydown', (e) => {
            const map = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 };
            if (e.key in map) { e.preventDefault(); stop(); select(current + map[e.key], true); }
            if (e.key === 'Home') { e.preventDefault(); stop(); select(0, true); }
            if (e.key === 'End') { e.preventDefault(); stop(); select(tabs.length - 1, true); }
        });
    });
    root.addEventListener('focusin', stop);

    if ('IntersectionObserver' in window) {
        new IntersectionObserver((entries) => {
            entries.forEach((e) => (e.isIntersecting ? start() : pause()));
        }, { threshold: 0.35 }).observe(root);
    }
}
