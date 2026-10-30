// Adnotările fotografiei de lentilă: un singur punct deschis odată, închidere cu Escape.
export default function lensAnatomy(root) {
    const pins = [...root.querySelectorAll('[data-pin]')];
    const closeAll = (except) => pins.forEach((p) => {
        if (p === except) return;
        p.querySelector('button').setAttribute('aria-expanded', 'false');
        p.querySelector('[data-note]').hidden = true;
    });
    pins.forEach((pin) => {
        const btn = pin.querySelector('button');
        const note = pin.querySelector('[data-note]');
        btn.addEventListener('click', () => {
            const open = btn.getAttribute('aria-expanded') !== 'true';
            closeAll(pin);
            btn.setAttribute('aria-expanded', String(open));
            note.hidden = !open;
        });
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeAll(null); });
}
