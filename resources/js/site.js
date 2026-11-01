// Comportamentul comun al siteului public. Paginile încarcă la cerere modulele lor (data-module="nume"),
// iar asistentul se încarcă doar la primul click — nimic greu în pagina inițială.
document.documentElement.classList.add('js');

const modules = import.meta.glob('./site/*.js');

function loadModules(root = document) {
    root.querySelectorAll('[data-module]').forEach((el) => {
        for (const name of el.dataset.module.split(' ')) {
            const loader = modules[`./site/${name}.js`];
            const key = `loaded${name.replace(/-(\w)/g, (_, c) => c.toUpperCase())}`; // dataset nu acceptă cratime
            if (loader && !el.dataset[key]) {
                el.dataset[key] = '1';
                loader().then((m) => m.default && m.default(el));
            }
        }
    });
}

// Meniul de pe mobil: sertar lateral, închidere cu Escape sau click în afară, focus păstrat.
function initMenu() {
    const toggle = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-menu]');
    if (!toggle || !menu) return;
    let backdrop = null;
    const close = () => {
        menu.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        backdrop?.remove();
        backdrop = null;
        document.body.style.overflow = '';
        toggle.focus();
    };
    toggle.addEventListener('click', () => {
        menu.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        backdrop = document.createElement('div');
        backdrop.className = 'nav-backdrop';
        backdrop.addEventListener('click', close);
        // În același context de suprapunere cu meniul (antetul are z-index propriu): fundalul rămâne SUB meniu.
        menu.parentNode.insertBefore(backdrop, menu);
        document.body.style.overflow = 'hidden';
        menu.querySelector('a, summary, button')?.focus();
    });
    menu.querySelector('[data-menu-close]')?.addEventListener('click', close);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && menu.classList.contains('is-open')) close();
    });
    // Pe desktop, un singur submeniu deschis odată; click în afară îl închide.
    document.addEventListener('click', (e) => {
        document.querySelectorAll('.main-nav__group[open]').forEach((d) => {
            if (!d.contains(e.target) && window.matchMedia('(min-width: 1100px)').matches) d.removeAttribute('open');
        });
    });
}

// Apariția la derulare: titlurile, cardurile și fotografiile secțiunilor intră ușor în pagină.
function initReveal() {
    const showAll = () => document.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-visible'));
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) { showAll(); return; }
    const targets = document.querySelectorAll('[data-reveal], .section h2, .section .lead, .steps li, .family, .card, .photo-row figure, .compare figure, .b2b-band > *, .story > *');
    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    targets.forEach((el, i) => {
        if (el.getBoundingClientRect().top < window.innerHeight * 0.9) { el.classList.add('is-visible'); return; } // ce se vede deja nu se ascunde
        el.setAttribute('data-reveal', '');
        el.style.transitionDelay = `${(i % 4) * 70}ms`;
        io.observe(el);
    });
}

function initAssistant() {
    const btn = document.querySelector('[data-assistant-launch]');
    if (!btn) return;
    btn.addEventListener('click', () => {
        import('./site/assistant.js').then((m) => m.default(btn));
    }, { once: true });
}

initMenu();
loadModules();
initReveal();
initAssistant();
