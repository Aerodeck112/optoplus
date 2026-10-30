{{-- Desenul dimensiunilor ramei: lățimea lentilei, puntea și lungimea brațului, cu valorile reale. --}}
<svg viewBox="0 0 520 250" role="img" aria-label="Lățimea lentilei {{ $spec->lens_width_mm }} mm{{ $spec->bridge_mm ? ', puntea '.$spec->bridge_mm.' mm' : '' }}{{ $spec->temple_mm ? ', brațul '.$spec->temple_mm.' mm' : '' }}">
    <g fill="none" stroke="currentColor" stroke-width="3">
        <rect x="50" y="40" width="170" height="110" rx="40"/>
        <rect x="300" y="40" width="170" height="110" rx="40"/>
        <path d="M220 72 q40 -22 80 0"/>
        <path d="M50 60 l-30 -6 M470 60 l30 -6"/>
    </g>
    <g class="dim" stroke-width="1.5">
        <line x1="50" y1="172" x2="220" y2="172"/><line x1="50" y1="164" x2="50" y2="180"/><line x1="220" y1="164" x2="220" y2="180"/>
        @if ($spec->bridge_mm)<line x1="220" y1="20" x2="300" y2="20"/><line x1="220" y1="12" x2="220" y2="28"/><line x1="300" y1="12" x2="300" y2="28"/>@endif
    </g>
    <text class="dim-label" x="135" y="198" text-anchor="middle">lentilă {{ $spec->lens_width_mm }} mm</text>
    @if ($spec->bridge_mm)<text class="dim-label" x="260" y="14" text-anchor="middle">punte {{ $spec->bridge_mm }} mm</text>@endif
    @if ($spec->temple_mm)
        <g transform="translate(60 222)">
            <path d="M0 0 h380 q20 0 30 14" fill="none" stroke="currentColor" stroke-width="3"/>
            <text x="190" y="-8" text-anchor="middle">braț {{ $spec->temple_mm }} mm</text>
        </g>
    @endif
</svg>
