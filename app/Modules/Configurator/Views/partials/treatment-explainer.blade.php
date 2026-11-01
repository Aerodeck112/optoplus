{{-- Comparații interactive pentru tratamente (ilustrative). $kind: antireflex | lumina_albastra | fotocromatic. --}}
@switch($kind)
@case('antireflex')
<div class="tx-demo" data-module="lens-explainer" data-explainer="toggle" data-treatment-ref="{{ $treatmentId }}">
    <svg viewBox="0 0 260 130" role="img" aria-label="Ilustrație: lentilă fără și cu antireflex">
        <rect width="260" height="130" rx="10" fill="#f5f8fa"/>
        <circle cx="26" cy="26" r="12" fill="#ffd979"/><path d="M26 6 v-4 M26 50 v-4 M6 26 h-4 M50 26 h-4" stroke="#e0b54b" stroke-width="2"/>
<g class="demo-face"><path d="M40 128 C40 60 80 22 130 22 C180 22 220 60 220 128 Z" fill="#f0d0b4"/><path d="M42 84 C40 40 84 14 130 14 C176 14 220 40 218 84 C204 54 170 42 130 42 C90 42 56 54 42 84 Z" fill="#3f2a1e"/><path d="M122 98 q8 8 16 0" stroke="#b07a63" stroke-width="2.5" fill="none" stroke-linecap="round"/><path d="M110 116 q20 10 40 0" stroke="#b5655a" stroke-width="3" fill="none" stroke-linecap="round"/><ellipse cx="94" cy="76" rx="11" ry="7" fill="#fff"/><circle cx="95" cy="76" r="5.5" fill="#3d6b8a"/><circle cx="95" cy="76" r="2.4" fill="#111"/><ellipse cx="166" cy="76" rx="11" ry="7" fill="#fff"/><circle cx="167" cy="76" r="5.5" fill="#3d6b8a"/><circle cx="167" cy="76" r="2.4" fill="#111"/></g>
        <g fill="#dceaf2" fill-opacity=".3" stroke="#053b5e" stroke-width="3.5"><rect x="62" y="56" width="62" height="42" rx="16"/><rect x="136" y="56" width="62" height="42" rx="16"/></g>
        <path d="M124 72 q6 -6 12 0 M62 70 L44 64 M198 70 L216 64" stroke="#053b5e" stroke-width="3" fill="none"/>
        <g class="demo-glare"><path d="M70 64 l30 -6 l6 6 l-30 6 Z M144 64 l30 -6 l6 6 l-30 6 Z" fill="#fff"/><path d="M84 88 l26 -6 l4 4 l-26 6 Z M158 88 l26 -6 l4 4 l-26 6 Z" fill="#fff" opacity=".85"/><circle cx="110" cy="64" r="6" fill="#fff4cc"/><circle cx="184" cy="64" r="6" fill="#fff4cc"/></g>
    </svg>
    <div class="tx-demo__controls"><button type="button" class="chip chip--small" data-demo="off" aria-pressed="true">Fără</button><button type="button" class="chip chip--small" data-demo="on" aria-pressed="false">Cu antireflex</button></div>
    <p class="small muted" aria-live="polite" data-demo-caption>Fără antireflex: lumina se reflectă în lentilă; ochii se văd mai greu, iar noaptea apar străluciri în jurul farurilor.</p>
</div>
@break
@case('lumina_albastra')
<div class="tx-demo" data-module="lens-explainer" data-explainer="toggle" data-treatment-ref="{{ $treatmentId }}">
    <svg viewBox="0 0 260 104" role="img" aria-label="Ilustrație: lumina de la ecran trece prin lentilă, fără și cu filtru">
        <rect width="260" height="104" rx="10" fill="#f5f8fa"/>
        <rect x="12" y="30" width="62" height="44" rx="4" fill="#3f4447"/><rect x="16" y="34" width="54" height="36" fill="#cfe6ff"/><rect x="34" y="74" width="18" height="10" fill="#5c6670"/>
        <g class="rays" stroke-width="3" stroke-linecap="round" fill="none">
            <path d="M74 44 H150" stroke="#6a4cc2"/><path d="M74 52 H150" stroke="#2f6fd6"/><path d="M74 60 H150" stroke="#2e9a5f"/><path d="M74 68 H150" stroke="#e0b54b"/>
            <path class="ray-filtered" d="M156 44 H222" stroke="#6a4cc2"/><path class="ray-filtered" d="M156 52 H222" stroke="#2f6fd6"/><path d="M156 60 H222" stroke="#2e9a5f"/><path d="M156 68 H222" stroke="#e0b54b"/>
        </g>
        <ellipse cx="153" cy="56" rx="6" ry="34" class="demo-filter" fill="#dceaf2" stroke="#053b5e" stroke-width="2"/>
        <g><ellipse cx="236" cy="56" rx="14" ry="10" fill="#fff" stroke="#9fb1bf"/><circle cx="234" cy="56" r="6" fill="#3d6b8a"/><circle cx="234" cy="56" r="2.6" fill="#111"/></g>
    </svg>
    <div class="tx-demo__controls"><button type="button" class="chip chip--small" data-demo="off" aria-pressed="true">Fără filtru</button><button type="button" class="chip chip--small" data-demo="on" aria-pressed="false">Cu filtru</button></div>
    <p class="small muted" aria-live="polite" data-demo-caption>Fără filtru: toată lumina de la ecran trece prin lentilă.</p>
</div>
@break
@case('fotocromatic')
<div class="tx-demo" data-module="lens-explainer" data-explainer="photo" data-treatment-ref="{{ $treatmentId }}">
    <svg viewBox="0 0 260 130" role="img" aria-label="Ilustrație: lentila fotocromatică se închide la lumina soarelui">
        <rect width="260" height="130" rx="10" class="photo-sky" fill="#e9eef2"/>
        <g class="photo-sun"><circle cx="222" cy="28" r="14" fill="#ffd34d"/><path d="M222 6 v-4 M222 54 v-4 M200 28 h-4 M248 28 h-4 M206 12 l-3 -3 M238 44 l3 3 M238 12 l3 -3 M206 44 l-3 3" stroke="#e0b54b" stroke-width="2"/></g>
        <g class="photo-room"><rect x="14" y="16" width="40" height="34" fill="#cfe0ea" stroke="#9fb1bf"/><path d="M34 16 V50 M14 33 H54" stroke="#9fb1bf"/><rect x="14" y="96" width="60" height="22" fill="#d8c3a5"/></g>
<g class="demo-face"><path d="M40 128 C40 60 80 22 130 22 C180 22 220 60 220 128 Z" fill="#f0d0b4"/><path d="M42 84 C40 40 84 14 130 14 C176 14 220 40 218 84 C204 54 170 42 130 42 C90 42 56 54 42 84 Z" fill="#3f2a1e"/><path d="M122 98 q8 8 16 0" stroke="#b07a63" stroke-width="2.5" fill="none" stroke-linecap="round"/><path d="M110 116 q20 10 40 0" stroke="#b5655a" stroke-width="3" fill="none" stroke-linecap="round"/><ellipse cx="94" cy="76" rx="11" ry="7" fill="#fff"/><circle cx="95" cy="76" r="5.5" fill="#3d6b8a"/><circle cx="95" cy="76" r="2.4" fill="#111"/><ellipse cx="166" cy="76" rx="11" ry="7" fill="#fff"/><circle cx="167" cy="76" r="5.5" fill="#3d6b8a"/><circle cx="167" cy="76" r="2.4" fill="#111"/></g>
        <g stroke="#053b5e" stroke-width="3.5"><rect x="62" y="56" width="62" height="42" rx="16" class="photo-tint" fill="#4a4f55" fill-opacity="0"/><rect x="136" y="56" width="62" height="42" rx="16" class="photo-tint" fill="#4a4f55" fill-opacity="0"/></g>
        <path d="M124 72 q6 -6 12 0 M62 70 L44 64 M198 70 L216 64" stroke="#053b5e" stroke-width="3" fill="none"/>
    </svg>
    <label class="tx-demo__range"><span>În casă</span><input type="range" min="0" max="100" value="0" data-photo-range aria-label="De la interior la exterior, la soare"><span>Afară, la soare</span></label>
    <p class="small muted" aria-live="polite" data-demo-caption>În casă: lentila este aproape transparentă.</p>
</div>
@break
@endswitch
