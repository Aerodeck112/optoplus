// Galeria produsului: miniaturi accesibile din tastatură și zoom la click (fără bibliotecă externă).
// 1.0.23: la ramele cu mai multe culori, alegerea culorii arată exact pozele acelei culori (prima devine imaginea
// mare); o culoare fără poze proprii arată pozele comune („Toate culorile” din panou).
// 1.0.25: mărirea folosește poza la rezoluție mare (originalul), încărcată abia la mărire, și se oprește la mărimea
// reală a pozei (fără pixeli); pe telefon se mută cu degetul. Butonul cu lupa deschide poza pe tot ecranul.
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
        main.dataset.zoom = btn.dataset.zoom || '';
        main.dataset.zoomW = btn.dataset.zoomW || '';
        delete img.dataset.hires; // imaginea afișată acum e varianta mică
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

    // --- mărire în pagină -------------------------------------------------------------------------------------
    const hires = (then) => {
        const url = main.dataset.zoom;
        if (!url || img.dataset.hires === url) { then(); return; }
        const pre = new Image();
        pre.onload = () => { img.removeAttribute('srcset'); img.src = url; img.dataset.hires = url; then(); };
        pre.onerror = then;
        pre.src = url;
        main.classList.add('is-loading');
    };
    const origin = (x, y) => {
        const r = main.getBoundingClientRect();
        img.style.transformOrigin = `${((x - r.left) / r.width) * 100}% ${((y - r.top) / r.height) * 100}%`;
    };
    const scale = () => {
        // cât de mult se poate mări fără pixeli: lățimea reală a pozei / lățimea afișată (object-fit: contain)
        const box = main.getBoundingClientRect();
        // (lățimea reală vine din server: Chrome micșorează naturalWidth după rezoluția din EXIF a unor JPEG-uri)
        const ratio = (img.naturalWidth && img.naturalHeight) ? img.naturalWidth / img.naturalHeight : box.width / box.height;
        const nw = Number(main.dataset.zoomW) || img.naturalWidth || box.width;
        const shown = Math.min(box.width, box.height * ratio);
        return Math.max(1.6, Math.min(3.5, nw / shown));
    };
    const zoomIn = (x, y) => {
        hires(() => {
            main.classList.remove('is-loading');
            main.style.setProperty('--zoom', scale().toFixed(2));
            if (x !== undefined) origin(x, y);
            main.classList.add('is-zoomed');
        });
    };
    img.addEventListener('click', (e) => {
        if (main.classList.contains('is-zoomed')) { main.classList.remove('is-zoomed'); return; }
        zoomIn(e.clientX || undefined, e.clientY || undefined);
    });
    main.addEventListener('mousemove', (e) => { if (main.classList.contains('is-zoomed')) origin(e.clientX, e.clientY); });
    main.addEventListener('touchmove', (e) => {
        if (!main.classList.contains('is-zoomed') || e.touches.length !== 1) return;
        e.preventDefault(); // pagina nu se derulează cât timp mutăm poza mărită
        origin(e.touches[0].clientX, e.touches[0].clientY);
    }, { passive: false });

    // --- pe tot ecranul ---------------------------------------------------------------------------------------
    const dialog = root.querySelector('[data-gallery-lightbox]');
    const full = root.querySelector('[data-gallery-full]');
    if (dialog && full && typeof dialog.showModal === 'function') {
        const big = dialog.querySelector('[data-lightbox-img]');
        const frame = dialog.querySelector('[data-lightbox-frame]');
        full.addEventListener('click', (e) => {
            e.stopPropagation();
            main.classList.remove('is-zoomed');
            big.src = main.dataset.zoom || img.currentSrc || img.src;
            big.alt = img.alt;
            frame.classList.remove('is-actual');
            dialog.showModal();
        });
        big.addEventListener('click', (e) => {
            const actual = frame.classList.toggle('is-actual');
            if (actual) {
                // centrăm pe punctul atins
                const r = big.getBoundingClientRect();
                const fx = (e.clientX - r.left) / r.width, fy = (e.clientY - r.top) / r.height;
                requestAnimationFrame(() => {
                    frame.scrollLeft = fx * frame.scrollWidth - frame.clientWidth / 2;
                    frame.scrollTop = fy * frame.scrollHeight - frame.clientHeight / 2;
                });
            }
        });
        dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
    } else if (full) {
        full.hidden = true;
    }
}
