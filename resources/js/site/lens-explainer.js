// Ilustrațiile interactive din configurator: „cum vezi prin lentilă”, grosimea după indice și comparațiile
// pentru tratamente. Totul e decorativ-explicativ: alegerea reală rămâne în formular, iar fără JavaScript
// desenele rămân statice, cu textele explicative alături.
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const GAZE_Y = { far: 0.22, mid: 0.55, near: 0.84 };          // poziția privirii pe lentilă (fracție din înălțime)
const TARGET = { far: [399, 70], mid: [408, 176], near: [298, 268] };
const LABEL = { far: 'departe', mid: 'la ecran', near: 'aproape' };

// Ce obiect e clar, pentru fiecare tip de lentilă și direcție a privirii.
function sharpness(family, gaze, mono) {
    if (family === 'monofocal') return gaze === mono;
    if (family === 'progresiv') return true;
    if (family === 'degresiv') return gaze !== 'far';
    if (family === 'bifocal') return gaze !== 'mid';
    return true;
}

function caption(family, gaze, mono) {
    const ok = sharpness(family, gaze, mono);
    const where = LABEL[gaze];
    const texts = {
        monofocal: ok
            ? `Monofocale pentru ${LABEL[mono]}: vezi clar ${where}, prin toată lentila.`
            : `Monofocale pentru ${LABEL[mono]}: ${where} imaginea nu e clară — lentila are o singură distanță de focalizare. Dacă ai nevoie de ochelari și pentru ${where}, ai nevoie de o a doua pereche sau de lentile progresive.`,
        progresiv: {
            far: 'Progresive: privind prin partea de sus, vezi clar departe (condus, stradă).',
            mid: 'Progresive: prin mijlocul lentilei vezi clar la distanța unui ecran. Coridorul e îngust: privești drept înainte.',
            near: 'Progresive: coborând privirea, vezi clar aproape (telefon, carte). Lateral, jos, imaginea e mai puțin clară — întorci puțin capul. Adaptarea durează câteva zile.',
        }[gaze],
        degresiv: ok
            ? `Degresive (de birou): vezi clar ${where}, cu o zonă largă, confortabilă pentru lucru.`
            : 'Degresive (de birou): departe imaginea nu e clară — nu sunt pentru condus sau pentru mers pe stradă.',
        bifocal: ok
            ? (gaze === 'far' ? 'Bifocale: prin partea mare vezi clar departe.' : 'Bifocale: prin segmentul de jos (vizibil pe lentilă) vezi clar aproape.')
            : 'Bifocale: la distanța unui ecran nu există o zonă dedicată — imaginea „sare” între departe și aproape.',
    };
    return texts[family];
}

function vision(root) {
    const scene = root.querySelector('.vx-scene');
    const gazeLine = root.querySelector('.vx-gaze');
    const hit = root.querySelector('.vx-hit');
    const dot = root.querySelector('.vx-dot');
    const cap = root.querySelector('.explain__caption');
    const monoTabs = root.querySelector('.vx-mono-tabs');
    let family = root.dataset.family || 'progresiv';
    let mono = root.dataset.mono || 'far';
    let gaze = 'far';
    let demo = null;

    const press = (selector, attr, value) => root.querySelectorAll(selector).forEach((b) => b.setAttribute('aria-pressed', String(b.dataset[attr] === value)));

    const render = () => {
        root.querySelectorAll('[data-zones]').forEach((z) => { z.style.display = z.dataset.zones === family ? '' : 'none'; });
        root.querySelectorAll('.vx-mono-label').forEach((t) => { t.textContent = `clar: ${LABEL[mono]}`; });
        monoTabs.hidden = family !== 'monofocal';
        const ok = sharpness(family, gaze, mono);
        scene.querySelectorAll('.vx-target').forEach((t) => {
            const looked = t.dataset.target === gaze;
            t.classList.toggle('is-looked', looked);
            t.setAttribute('filter', looked && !ok ? 'url(#vx-blur)' : '');
        });
        const [tx, ty] = TARGET[gaze];
        const lensY = 122 + 52 * GAZE_Y[gaze];
        const d = `M172 146 L189 ${lensY.toFixed(1)} L${tx} ${ty}`;
        gazeLine.setAttribute('d', d);
        gazeLine.style.d = `path("${d}")`; // tranziție lină unde browserul o permite
        gazeLine.classList.toggle('is-bad', !ok);
        const dotY = 14 + 132 * GAZE_Y[gaze];
        hit.setAttribute('cy', lensY.toFixed(1));
        hit.style.cy = `${lensY.toFixed(1)}px`;
        dot.setAttribute('cy', dotY.toFixed(1));
        dot.style.cy = `${dotY.toFixed(1)}px`;
        dot.classList.toggle('is-bad', !ok);
        cap.textContent = caption(family, gaze, mono);
        press('[data-set-family]', 'setFamily', family);
        press('[data-set-gaze]', 'setGaze', gaze);
        press('[data-set-mono]', 'setMono', mono);
    };

    const stopDemo = () => { clearInterval(demo); demo = null; };
    root.addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        stopDemo();
        if (b.dataset.setFamily) family = b.dataset.setFamily;
        if (b.dataset.setGaze) gaze = b.dataset.setGaze;
        if (b.dataset.setMono) { mono = b.dataset.setMono; gaze = mono; }
        render();
    });

    // Alegerea unei lentile din listă schimbă desenul pe familia ei.
    root.closest('form')?.addEventListener('change', (e) => {
        if (e.target.name === 'oferta' && e.target.dataset.family) {
            family = e.target.dataset.family;
            render();
        }
    });

    // Demonstrație: privirea coboară singură până la prima interacțiune (nu și cu „reducere mișcare”).
    if (!reduce) {
        const order = ['far', 'mid', 'near'];
        const io = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && demo === null) {
                demo = setInterval(() => { gaze = order[(order.indexOf(gaze) + 1) % 3]; render(); }, 2600);
                io.disconnect();
            }
        }, { threshold: 0.5 });
        io.observe(root);
    }
    render();
}

function thickness(root) {
    const form = root.closest('form');
    const sync = () => {
        const sel = form?.querySelector('input[name="oferta"]:checked');
        root.querySelectorAll('.tx-lens').forEach((g) => g.classList.toggle('is-selected', !!sel && g.dataset.index === sel.dataset.index));
    };
    form?.addEventListener('change', (e) => { if (e.target.name === 'oferta') sync(); });
    requestAnimationFrame(() => root.classList.add('is-drawn'));
    sync();
}

// Comparație „fără / cu” pentru antireflex și filtrul de lumină albastră, legată în ambele sensuri de bifa din
// formular: „Cu filtru” / „Cu antireflex” alege tratamentul (prețul se recalculează pe server), „Fără” îl scoate.
function toggle(root) {
    const checkbox = document.querySelector(`[data-treatment="${root.dataset.treatmentRef}"]`);
    const cap = root.querySelector('[data-demo-caption]');
    const texts = {
        off: cap.textContent,
        on: root.querySelector('.demo-glare')
            ? 'Cu antireflex: reflexiile scad mult; ochii se văd natural, iar în lumina farurilor apar mai puține străluciri.'
            : 'Cu filtru: o parte din lumina albastră-violet este reținută de lentilă; restul culorilor trec. Ilustrație, nu o măsurătoare.',
    };
    const set = (state) => {
        root.classList.toggle('is-on', state === 'on');
        root.querySelectorAll('[data-demo]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.demo === state)));
        cap.textContent = texts[state];
    };
    root.addEventListener('click', (e) => {
        const b = e.target.closest('[data-demo]');
        if (!b) return;
        set(b.dataset.demo);
        const want = b.dataset.demo === 'on';
        if (checkbox && !checkbox.disabled && checkbox.checked !== want) {
            checkbox.checked = want;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
    checkbox?.addEventListener('change', () => set(checkbox.checked ? 'on' : 'off'));
    set(checkbox?.checked ? 'on' : 'off');
}

// Fotocromatic: „În casă / În mașină / Afară, la soare”, în nuanța și intensitatea alese (ilustrativ).
// Helio se închide doar afară (parbrizul oprește UV); Xtractive se închide și în mașină.
const TINTS = { gri: '#454a50', maro: '#6b4a2f', verde: '#3f5a45', albastru: '#2f4c6e', mov: '#5a3f6e' };
function photo(root) {
    const cap = root.querySelector('[data-demo-caption]');
    const fieldset = document.querySelector(`[data-option-for="${root.dataset.treatmentRef}"]`);
    const xtractive = root.dataset.variant === 'xtractive';
    const sun = root.querySelector('.photo-sun');
    const room = root.querySelector('.photo-room');
    const sky = root.querySelector('.photo-sky');
    const car = root.querySelector('.photo-car');
    const chips = [...root.querySelectorAll('[data-scene]')];
    let scene = 'casa';
    const render = () => {
        const code = fieldset?.querySelector('input:checked')?.value || '';
        const [color, pct] = code.split('_');
        const max = (Number(pct) || 80) / 100;
        const level = { casa: 0, masina: xtractive ? 0.6 : 0.06, afara: 1 }[scene];
        root.querySelectorAll('.photo-tint').forEach((el) => { el.setAttribute('fill', TINTS[color] || TINTS.gri); el.setAttribute('fill-opacity', (level * max).toFixed(2)); });
        sun.style.opacity = scene === 'casa' ? '0.15' : '1';
        room.style.opacity = scene === 'casa' ? '1' : '0';
        car.setAttribute('opacity', scene === 'masina' ? '1' : '0');
        sky.setAttribute('fill', scene === 'casa' ? '#e9eef2' : '#cfe6f7');
        chips.forEach((c) => c.setAttribute('aria-pressed', String(c.dataset.scene === scene)));
        const shade = code ? ` în nuanța aleasă (până la aproximativ ${Math.round(max * 100)}%)` : '';
        cap.textContent = {
            casa: 'În casă: lentila este aproape transparentă.',
            masina: xtractive
                ? 'În mașină: Xtractive se închide și în spatele parbrizului, deci vă ajută și la volan. Cât de mult depinde de lumină și de temperatură.'
                : 'În mașină: Helio rămâne aproape transparent, pentru că parbrizul oprește razele UV care îl închid. Pentru condus, alegeți Xtractive.',
            afara: `Afară, la soare: lentila se închide${shade}. Viteza reală depinde de lumina UV și de temperatură.`,
        }[scene];
    };
    root.addEventListener('click', (e) => { const b = e.target.closest('[data-scene]'); if (b) { root.dataset.touched = '1'; scene = b.dataset.scene; render(); } });
    fieldset?.addEventListener('change', () => { if (scene === 'casa') scene = 'afara'; render(); });
    // La prima afișare: o scurtă trecere casă → afară → (mașină) → casă.
    if (!reduce) {
        const io = new IntersectionObserver((entries) => {
            if (!entries[0].isIntersecting) return;
            io.disconnect();
            ['afara', 'masina', 'casa'].forEach((sc, i) => setTimeout(() => { if (!root.dataset.touched) { scene = sc; render(); } }, 900 * (i + 1)));
        }, { threshold: 0.6 });
        io.observe(root);
    }
    render();
}

export default function lensExplainer(root) {
    ({ vision, thickness, toggle, photo })[root.dataset.explainer]?.(root);
}
