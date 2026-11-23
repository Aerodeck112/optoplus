// Galeria produsului: miniaturi accesibile din tastatură și zoom la click (fără bibliotecă externă).
// 1.0.23: la ramele cu mai multe culori, alegerea culorii arată exact pozele acelei culori (prima devine imaginea
// mare); o culoare fără poze proprii arată pozele comune („Toate culorile” din panou).
export default function gallery(root) {
    const main = root.querySelector('[data-gallery-main]');
    const img = main?.querySelector('img');
    if (!main || !img) return;
    const thumbs = [...root.querySelectorAll('[data-gallery-thumb]')];

    const show = (btn) => {
        thumbs.forEach((b) => b.setAttribute('aria-current', 'false'));
        btn.setAttribute('aria-current', 'true');
        img.src = btn.dataset.src;
        if (btn.dataset.srcset) img.srcset = btn.dataset.srcset; else img.removeAttribute('srcset');
        img.alt = btn.dataset.alt || img.alt;
        main.classList.remove('is-zoomed');
    };
    thumbs.forEach((btn) => btn.addEventListener('click', () => show(btn)));

    if ('byColor' in root.dataset) {
        const form = document.getElementById('buy-form');
        const strip = root.querySelector('.gallery__thumbs');
        const pick = (variantId, change) => {
            const own = thumbs.filter((b) => b.dataset.variant === variantId);
            // culoarea are poze proprii → doar ele; altfel pozele comune
            thumbs.forEach((b) => { b.hidden = own.length ? b.dataset.variant !== variantId : b.dataset.variant !== ''; });
            // pozele culorii alese primele, apoi cele comune
            [...own].reverse().forEach((b) => strip?.prepend(b));
            const first = own[0] || thumbs.find((b) => !b.hidden);
            if (change && first) show(first);
        };
        form?.addEventListener('change', (e) => {
            if (e.target.name === 'varianta') pick(e.target.value, true);
        });
        // browserul poate reține altă culoare bifată la revenirea pe pagină
        const checked = form?.querySelector('input[name="varianta"]:checked');
        const current = checked ? checked.value : root.dataset.defaultVariant;
        pick(current, current !== root.dataset.defaultVariant);
    }

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
