{{--
    „Cum vezi prin lentilă”: desen animat interactiv. Personajul privește departe, la ecran sau aproape; pentru
    fiecare familie de lentile se vede prin ce zonă a lentilei trece privirea și ce obiect e clar. Ilustrativ,
    nu la scară. Fără JavaScript rămâne un desen static (progresive, privire departe) cu explicațiile de mai jos.
--}}
@php($focus = ['distanta' => 'far', 'aproape' => 'near', 'calculator' => 'mid'][$usage ?? ''] ?? 'far')
<section class="explain" data-module="lens-explainer" data-explainer="vision" data-family="{{ $family ?? 'progresiv' }}" data-mono="{{ $focus }}" aria-labelledby="vx-title">
    <div class="explain__head">
        <h2 id="vx-title">Cum vezi prin fiecare tip de lentilă</h2>
        <span class="illustrative">Desen ilustrativ, nu la scară</span>
    </div>
    <div class="explain__tabs" role="group" aria-label="Tipul de lentilă">
        @foreach (['monofocal' => 'Monofocale', 'progresiv' => 'Progresive', 'degresiv' => 'Degresive (birou)', 'bifocal' => 'Bifocale'] as $code => $label)
            <button type="button" class="chip" data-set-family="{{ $code }}" aria-pressed="{{ ($family ?? 'progresiv') === $code ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>
    <div class="explain__stage">
        <svg class="vx-scene" viewBox="0 0 520 330" role="img" aria-labelledby="vx-caption">
            <defs>
                <filter id="vx-blur" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="3.2"/></filter>
                <linearGradient id="vx-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#dfeaf2"/><stop offset="1" stop-color="#f5f8fa"/></linearGradient>
            </defs>
            <rect width="520" height="330" fill="url(#vx-sky)" rx="14"/>
            {{-- Ținta „departe”: munți și un indicator rutier --}}
            <g class="vx-target" data-target="far">
                <path d="M300 112 L350 52 L384 88 L414 40 L470 112 Z" fill="#a9c1d3"/>
                <path d="M414 40 L426 55 L404 58 Z" fill="#fff"/>
                <rect x="396" y="70" width="4" height="44" fill="#5c6670"/>
                <rect x="368" y="58" width="62" height="22" rx="3" fill="#2e6b4f"/>
                <text x="399" y="73" text-anchor="middle" font-size="10" font-weight="700" fill="#fff">CENTRU 2 km</text>
            </g>
            {{-- Ținta „la ecran”: un laptop --}}
            <g class="vx-target" data-target="mid">
                <rect x="352" y="140" width="112" height="70" rx="6" fill="#3f4447"/>
                <rect x="358" y="146" width="100" height="58" rx="2" fill="#f5f8fa"/>
                <rect x="364" y="152" width="44" height="6" rx="2" fill="#053b5e"/>
                <rect x="364" y="163" width="86" height="4" rx="2" fill="#9fb1bf"/>
                <rect x="364" y="171" width="78" height="4" rx="2" fill="#9fb1bf"/>
                <rect x="364" y="182" width="12" height="16" fill="#a15f0e"/><rect x="380" y="176" width="12" height="22" fill="#2e6b4f"/><rect x="396" y="186" width="12" height="12" fill="#053b5e"/>
                <path d="M340 210 H476 L466 220 H350 Z" fill="#5c6670"/>
            </g>
            {{-- Ținta „aproape”: telefon/carte ținute în mână --}}
            <g class="vx-target" data-target="near">
                <rect x="250" y="236" width="96" height="66" rx="5" fill="#fff" stroke="#9fb1bf"/>
                <path d="M298 236 V302" stroke="#cdd8e0"/>
                <rect x="258" y="246" width="32" height="3" fill="#3f4447"/><rect x="258" y="254" width="34" height="3" fill="#9fb1bf"/><rect x="258" y="262" width="30" height="3" fill="#9fb1bf"/><rect x="258" y="270" width="33" height="3" fill="#9fb1bf"/>
                <rect x="306" y="246" width="32" height="3" fill="#3f4447"/><rect x="306" y="254" width="30" height="3" fill="#9fb1bf"/><rect x="306" y="262" width="34" height="3" fill="#9fb1bf"/><rect x="306" y="270" width="28" height="3" fill="#9fb1bf"/>
                <path d="M244 296 q-10 10 4 18 h22 l6 -14 Z" fill="#e8c4a6"/>
            </g>
            {{-- Personajul, din profil --}}
            <g class="vx-person">
                <path d="M40 330 C40 260 70 236 120 236 C170 236 196 262 196 330 Z" fill="#053b5e"/>
                <rect x="104" y="200" width="30" height="44" rx="10" fill="#e8c4a6"/>
                <path d="M76 150 C76 104 108 84 142 88 C176 92 194 118 192 150 C192 166 198 172 204 182 C206 186 202 190 196 190 L192 192 C194 204 190 214 176 216 C160 218 150 222 140 224 C104 226 76 196 76 150 Z" fill="#f0d0b4"/>
                <path d="M74 140 C70 98 104 74 146 80 C176 84 196 104 196 128 C182 112 160 106 136 112 C112 118 96 130 92 158 C84 158 76 152 74 140 Z" fill="#3f2a1e"/>
                <circle cx="172" cy="146" r="3.4" fill="#1f2a33" class="vx-eye"/>
                <path d="M164 136 q8 -4 16 0" stroke="#3f2a1e" stroke-width="2.5" fill="none" stroke-linecap="round"/>
                <path d="M178 182 q8 2 12 -2" stroke="#b07a63" stroke-width="2" fill="none" stroke-linecap="round"/>
                {{-- Lentila văzută din profil, cu zonele colorate --}}
                <rect x="186" y="122" width="6" height="52" rx="3" fill="#cfe0ea" stroke="#053b5e" stroke-width="1.5"/>
                <path d="M140 132 L186 128" stroke="#053b5e" stroke-width="2"/>
            </g>
            {{-- Linia privirii, animată --}}
            <path class="vx-gaze" d="M172 146 L399 70" stroke="#a15f0e" stroke-width="2.5" stroke-dasharray="6 6" fill="none"/>
            <circle class="vx-hit" cx="189" cy="132" r="5" fill="#a15f0e"/>
        </svg>
        <div class="vx-side">
            <svg class="vx-lens" viewBox="0 0 200 160" role="img" aria-label="Lentila văzută din față, cu zonele de vedere">
                <defs><clipPath id="vx-clip"><ellipse cx="100" cy="80" rx="88" ry="66"/></clipPath></defs>
                <ellipse cx="100" cy="80" rx="88" ry="66" fill="#eef3f7"/>
                <g clip-path="url(#vx-clip)" font-size="10" fill="#3f4447" text-anchor="middle">
                    <g class="vx-zones" data-zones="monofocal"><rect width="200" height="160" class="z-sharp"/><text x="100" y="84" class="vx-mono-label">clar la o singură distanță</text></g>
                    <g class="vx-zones" data-zones="progresiv">
                        <rect width="200" height="74" class="z-far"/>
                        <path d="M84 74 C88 92 90 100 78 160 L122 160 C110 100 112 92 116 74 Z" class="z-mid"/>
                        <path d="M62 160 C72 122 128 122 138 160 Z" class="z-near"/>
                        <path d="M0 74 H84 C88 92 80 120 62 160 H0 Z M200 74 H116 C112 92 120 120 138 160 H200 Z" class="z-blur"/>
                        <text x="100" y="44">departe</text><text x="100" y="100">ecran</text><text x="100" y="141">aproape</text>
                    </g>
                    <g class="vx-zones" data-zones="degresiv">
                        <rect width="200" height="70" class="z-mid"/><rect y="70" width="200" height="90" class="z-near"/>
                        <text x="100" y="44">ecran, birou</text><text x="100" y="118">aproape</text>
                    </g>
                    <g class="vx-zones" data-zones="bifocal">
                        <rect width="200" height="160" class="z-far"/><circle cx="100" cy="150" r="42" class="z-near" stroke="#053b5e" stroke-width="1.5"/>
                        <text x="100" y="54">departe</text><text x="100" y="136">aproape</text>
                    </g>
                </g>
                <ellipse cx="100" cy="80" rx="88" ry="66" fill="none" stroke="#053b5e" stroke-width="3"/>
                <circle class="vx-dot" cx="100" cy="40" r="6" fill="#a15f0e" stroke="#fff" stroke-width="2"/>
            </svg>
            <div class="explain__tabs" role="group" aria-label="Unde privești">
                <button type="button" class="chip chip--small" data-set-gaze="far" aria-pressed="true">Departe</button>
                <button type="button" class="chip chip--small" data-set-gaze="mid" aria-pressed="false">La ecran</button>
                <button type="button" class="chip chip--small" data-set-gaze="near" aria-pressed="false">Aproape</button>
            </div>
            <div class="explain__tabs vx-mono-tabs" role="group" aria-label="Monofocale pentru">
                <span class="small muted">Monofocale pentru:</span>
                <button type="button" class="chip chip--small" data-set-mono="far" aria-pressed="{{ $focus === 'far' ? 'true' : 'false' }}">departe</button>
                <button type="button" class="chip chip--small" data-set-mono="mid" aria-pressed="{{ $focus === 'mid' ? 'true' : 'false' }}">ecran</button>
                <button type="button" class="chip chip--small" data-set-mono="near" aria-pressed="{{ $focus === 'near' ? 'true' : 'false' }}">citit</button>
            </div>
        </div>
    </div>
    <p class="explain__caption" id="vx-caption" aria-live="polite">Lentilele progresive: privind în sus vezi clar departe; prin mijloc, la ecran; în partea de jos, aproape. Lateral, jos, imaginea e mai puțin clară — întorci puțin capul.</p>
</section>
