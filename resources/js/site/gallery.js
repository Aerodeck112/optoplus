// Galeria produsului: miniaturi accesibile din tastatură și zoom la click (fără bibliotecă externă).
export default function gallery(root) {
    const main = root.querySelector('[data-gallery-main]');
    const img = main?.querySelector('img');
    if (!main || !img) return;
    root.querySelectorAll('[data-gallery-thumb]').forEach((btn) => {
        btn.addEventListener('click', () => {
            root.querySelectorAll('[data-gallery-thumb]').forEach((b) => b.setAttribute('aria-current', 'false'));
            btn.setAttribute('aria-current', 'true');
            img.src = btn.dataset.src;
            if (btn.dataset.srcset) img.srcset = btn.dataset.srcset; else img.removeAttribute('srcset');
            img.alt = btn.dataset.alt || img.alt;
            main.classList.remove('is-zoomed');
        });
    });
    const zoom = (e) => {
        main.classList.toggle('is-zoomed');
        if (main.classList.contains('is-zoomed') && e.clientX) {
            const r = main.getBoundingClientRect();
            img.style.transformOrigin = `${((e.clientX - r.left) / r.width) * 100}% ${((e.clientY - r.top) / r.height) * 100}%`;
        }
    };
    img.addEventListener('click', zoom);
    main.addEventListener('mousemove', (e) => {
        if (!main.classList.contains('is-zoomed')) return;
        const r = main.getBoundingClientRect();
        img.style.transformOrigin = `${((e.clientX - r.left) / r.width) * 100}% ${((e.clientY - r.top) / r.height) * 100}%`;
    });
}
