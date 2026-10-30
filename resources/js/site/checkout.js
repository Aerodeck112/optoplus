// Finalizarea comenzii: afișează câmpurile condiționate și recalculează totalul la schimbarea livrării.
// Fără JavaScript, toate câmpurile sunt vizibile și există butonul „Recalculează totalul”.
export default function checkout(form) {
    const conditional = form.querySelectorAll('[data-show-if]');
    const sync = () => {
        conditional.forEach((el) => {
            const [name, value] = el.dataset.showIf.split('=');
            const input = form.querySelector(`[name="${name}"]:checked`);
            el.hidden = !(input && input.value === value);
        });
    };
    form.addEventListener('change', (e) => {
        sync();
        if (e.target.matches('[data-recalc]')) {
            const btn = document.createElement('input');
            btn.type = 'hidden';
            btn.name = 'actiune';
            btn.value = 'recalculeaza';
            form.appendChild(btn);
            form.noValidate = true;
            form.submit();
        }
    });
    let sent = false;
    form.addEventListener('submit', (e) => {
        if (sent) {
            e.preventDefault(); // dublu-click: serverul ar refolosi oricum cheia de finalizare
            return;
        }
        sent = true;
        setTimeout(() => { sent = false; }, 8000);
    });
    sync();
}
