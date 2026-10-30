// Configuratorul: recalcul de preț pe server la fiecare alegere (fără salvare), afișarea AX doar cu CYL,
// comutarea DP o valoare / pe ochi, nuanța fotocromaticului doar când tratamentul e bifat.
// Fără JavaScript, formularele funcționează normal (prețul se actualizează la fiecare pas).
export default function configurator(root) {
    const url = root.dataset.calcUrl;
    const step = root.dataset.step;
    const form = root.querySelector('form.form');
    const summary = root.querySelector('[data-summary]');
    const totals = root.querySelectorAll('[data-total]');
    const errorsBox = root.querySelector('[data-calc-errors]');
    const token = document.querySelector('input[name="_token"]')?.value;
    let timer = null;
    let controller = null;

    root.querySelector('[data-focus-on-load]')?.focus();

    const recalc = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            controller?.abort();
            controller = new AbortController();
            const data = new FormData(form);
            data.set('pas', step);
            try {
                const res = await fetch(url, { method: 'POST', body: data, signal: controller.signal, headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } });
                if (!res.ok) return;
                const json = await res.json();
                summary.innerHTML = json.summary;
                totals.forEach((t) => { t.textContent = json.total; t.classList.remove('is-updated'); void t.offsetWidth; t.classList.add('is-updated'); });
                if (errorsBox) {
                    errorsBox.hidden = json.errors.length === 0;
                    errorsBox.textContent = json.errors.join(' ');
                }
            } catch (e) { /* rețeaua a căzut: prețul se recalculează oricum la trimiterea pasului */ }
        }, 250);
    };
    form?.querySelectorAll('[data-calc]').forEach((el) => el.addEventListener('change', recalc));

    // Opțiunile tratamentelor apar doar pentru tratamentele bifate.
    const syncOptions = () => form?.querySelectorAll('[data-option-for]').forEach((fs) => {
        const cb = form.querySelector(`[data-treatment="${fs.dataset.optionFor}"]`);
        fs.hidden = !cb?.checked;
    });
    form?.querySelectorAll('[data-treatment]').forEach((cb) => cb.addEventListener('change', syncOptions));
    syncOptions();

    // AX are sens doar cu CYL.
    form?.querySelectorAll('[data-cyl]').forEach((cyl) => {
        const axis = form.querySelector(`[data-axis="${cyl.dataset.cyl}"]`);
        const sync = () => {
            const has = cyl.value.trim() !== '' && !/^[+-]?0+([.,]0+)?$/.test(cyl.value.trim());
            axis.disabled = !has && axis.value.trim() === '';
            axis.placeholder = has ? '0–180' : '—';
        };
        cyl.addEventListener('input', sync);
        sync();
    });

    // DP: o singură valoare sau câte una pe ochi.
    const pdRadios = form?.querySelectorAll('[data-pd-mode]') ?? [];
    const syncPd = () => {
        const mode = [...pdRadios].find((r) => r.checked)?.value || 'binocular';
        form.querySelectorAll('[data-pd]').forEach((f) => { f.hidden = f.dataset.pd !== mode; });
    };
    pdRadios.forEach((r) => r.addEventListener('change', syncPd));
    if (pdRadios.length) syncPd();
}
