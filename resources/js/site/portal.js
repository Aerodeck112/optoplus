// Portalul partenerilor: rânduri noi în comanda rapidă și reîncărcarea totalului la schimbarea livrării.
export default function portal(root) {
    root.querySelectorAll('[data-reload-param]').forEach((input) => {
        input.addEventListener('change', () => {
            const url = new URL(location.href);
            url.searchParams.set(input.dataset.reloadParam, input.value);
            location.assign(url.toString());
        });
    });
    const add = root.querySelector('[data-add-row]');
    const body = root.querySelector('table.qo tbody');
    if (add && body) {
        add.addEventListener('click', () => {
            const rows = body.querySelectorAll('tr');
            if (rows.length >= 50) return;
            const clone = rows[rows.length - 1].cloneNode(true);
            const index = rows.length;
            clone.classList.remove('is-invalid');
            clone.querySelectorAll('input, select').forEach((el) => {
                el.name = el.name.replace(/r\[\d+\]/, `r[${index}]`);
                if (el.tagName === 'INPUT') el.value = el.name.endsWith('[cantitate]') ? '1' : '';
                if (el.getAttribute('aria-label')) el.setAttribute('aria-label', el.getAttribute('aria-label').replace(/\d+$/, String(index + 1)));
            });
            body.appendChild(clone);
            clone.querySelector('input')?.focus();
        });
    }
}
