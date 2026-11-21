// Propunerea limbii țării vizitatorului (1.0.22). Nu mutăm pe nimeni fără acord: regulile UE (geo-blocking)
// cer consimțământul explicit al clientului. Serverul notează propunerea în cookie-ul „optoplus_sugestie”.
let DICT = null;
const t = (text) => {
    if (DICT === null) {
        try { DICT = JSON.parse(document.getElementById('i18n-js')?.textContent || '{}'); } catch { DICT = {}; }
    }
    return DICT[text] ?? text;
};

// Textul propunerii e scris în limba propusă (vizitatorul o înțelege), butonul „rămân” în limba paginii.
const OFFERS = {
    hu: { text: 'Magyarországról látogat? Nézze meg az oldalt magyarul, forintos árakkal.', go: 'Tovább magyarul' },
    en: { text: 'Visiting from outside Romania? See the site in English, with prices in euro.', go: 'Continue in English' },
    ro: { text: 'Sunteți în România? Vedeți siteul în română, cu prețuri în lei.', go: 'Continuați în română' },
};

const cookie = (name) => document.cookie.split('; ').find((c) => c.startsWith(name + '='))?.split('=')[1] ?? null;
const forget = () => { document.cookie = 'optoplus_sugestie=; Max-Age=0; path=/; SameSite=Lax'; };

export default function regionSuggest(root) {
    const target = cookie('optoplus_sugestie');
    const current = (document.documentElement.lang || 'ro').slice(0, 2);
    const offer = OFFERS[target];
    if (!offer || target === current) {
        forget();
        return;
    }
    const path = location.pathname.replace(/^\/(en|hu)(?=\/|$)/, '') || '/';
    const href = (target === 'ro' ? '' : '/' + target) + (path === '/' && target !== 'ro' ? '/' : path) + location.search;

    root.innerHTML = '';
    const box = document.createElement('div');
    box.className = 'region-suggest';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-live', 'polite');
    box.setAttribute('aria-label', t('Alegeți limba siteului'));
    const p = document.createElement('p');
    p.lang = target;
    p.textContent = offer.text;
    const actions = document.createElement('div');
    actions.className = 'region-suggest__actions';
    const go = document.createElement('a');
    go.className = 'btn btn--primary btn--small';
    go.href = href;
    go.lang = target;
    go.textContent = offer.go;
    go.setAttribute('data-no-localize', '');
    go.addEventListener('click', forget);
    const stay = document.createElement('button');
    stay.type = 'button';
    stay.className = 'btn btn--ghost btn--small';
    stay.textContent = t('Rămân pe această versiune');
    stay.addEventListener('click', () => { forget(); box.remove(); });
    actions.append(go, stay);
    box.append(p, actions);
    root.append(box);
    root.hidden = false;
    go.focus({ preventScroll: true });
}
