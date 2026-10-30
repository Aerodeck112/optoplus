// Filtrele catalogului: pe mobil se deschid dintr-un buton; schimbarea unei bife trimite formularul (GET).
export default function filters(root) {
    const toggle = root.querySelector('[data-filters-toggle]');
    const panel = root.querySelector('[data-filters-panel]');
    toggle?.addEventListener('click', () => {
        const open = panel.hasAttribute('hidden');
        panel.toggleAttribute('hidden', !open);
        toggle.setAttribute('aria-expanded', String(open));
    });
    root.querySelectorAll('form[data-autosubmit]').forEach((form) => {
        form.addEventListener('change', (e) => {
            if (e.target.matches('input[type="checkbox"], select')) form.requestSubmit();
        });
    });
}
