{{-- Schemă ilustrativă a zonelor de vedere pentru fiecare familie (nu la scară). --}}
<svg class="family__diagram" viewBox="0 0 180 120" role="img" aria-label="Schema zonelor de vedere ale lentilelor {{ $code }}">
    <defs><clipPath id="fd-{{ $code }}"><ellipse cx="90" cy="60" rx="80" ry="54"/></clipPath></defs>
    <ellipse cx="90" cy="60" rx="80" ry="54" fill="#eef3f7" stroke="currentColor" stroke-width="2"/>
    <g clip-path="url(#fd-{{ $code }})" font-family="Hanken Grotesk, sans-serif" font-size="10" fill="#3f4447">
    @switch($code)
        @case('monofocal')
            <rect x="0" y="0" width="180" height="120" fill="#d6e4ee"/>
            <text x="90" y="64" text-anchor="middle">o singură distanță</text>
            @break
        @case('progresiv')
            <rect x="0" y="0" width="180" height="58" fill="#d6e4ee"/>
            <path d="M78 58 C82 74, 84 82, 72 120 L108 120 C96 82, 98 74, 102 58 Z" fill="#c4d8e6"/>
            <path d="M60 120 C70 92, 110 92, 120 120 Z" fill="#b1cbdd"/>
            <text x="90" y="34" text-anchor="middle">distanță</text>
            <text x="90" y="80" text-anchor="middle">intermediar</text>
            <text x="90" y="108" text-anchor="middle">aproape</text>
            @break
        @case('degresiv')
            <rect x="0" y="0" width="180" height="50" fill="#e6eef4"/>
            <rect x="0" y="50" width="180" height="70" fill="#c4d8e6"/>
            <text x="90" y="32" text-anchor="middle">ecran, birou</text>
            <text x="90" y="92" text-anchor="middle">aproape</text>
            @break
        @case('bifocal')
            <rect x="0" y="0" width="180" height="120" fill="#d6e4ee"/>
            <circle cx="90" cy="112" r="34" fill="#b1cbdd" stroke="currentColor" stroke-width="1.5"/>
            <text x="90" y="44" text-anchor="middle">distanță</text>
            <text x="90" y="100" text-anchor="middle">aproape</text>
            @break
    @endswitch
    </g>
</svg>
