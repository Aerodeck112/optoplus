// Reîncarcă pagina comenzii cât timp așteptăm confirmarea plății (maximum câteva încercări).
export default function refresh(el) {
    const seconds = Number(el.dataset.refresh || 15);
    const key = `refresh:${location.pathname}`;
    const count = Number(sessionStorage.getItem(key) || 0);
    if (count >= 8) return;
    setTimeout(() => { sessionStorage.setItem(key, String(count + 1)); location.reload(); }, seconds * 1000);
}
