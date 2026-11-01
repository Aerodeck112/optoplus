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

function initAssistant() {
    const btn = document.querySelector('[data-assistant-launch]');
    if (!btn) return;
    btn.addEventListener('click', () => {
        import('./site/assistant.js').then((m) => m.default(btn));
    }, { once: true });
}

initMenu();
loadModules();
initAssistant();
