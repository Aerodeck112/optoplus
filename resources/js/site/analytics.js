// Evenimentul „purchase”: serverul îl emite o singură dată pe comandă. Se trimite doar dacă există un strat de
// analiză încărcat (cu acordul vizitatorului); nu conține date despre rețetă.
export default function analytics(el) {
    try {
        const data = JSON.parse(el.dataset.purchase || 'null');
        if (data && Array.isArray(window.dataLayer)) {
            window.dataLayer.push({ event: 'purchase', ecommerce: data });
        }
    } catch {
        // date invalide: nimic de trimis
    }
}
