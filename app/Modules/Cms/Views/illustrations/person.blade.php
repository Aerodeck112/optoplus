{{-- Ilustrație vectorială: o persoană care poartă ochelari (desen, nu fotografie; nu reprezintă un client real).
     $variant: femeie | barbat | copil | senior | fotocromatic. Culori din paleta Optoplus. Decorativ (aria-hidden),
     textul echivalent e în banner. --}}
@php
    $v = $variant ?? 'femeie';
    $p = [
        'femeie' => ['skin' => '#f1c7a5', 'shade' => '#e0ab86', 'hair' => '#5a3522', 'style' => 'lung', 'glasses' => 'cateye', 'frame' => '#1d2a33', 'shirt' => '#2f8fd8', 'bg' => '#ffffff'],
        'barbat' => ['skin' => '#d9a37f', 'shade' => '#c48a65', 'hair' => '#2b2420', 'style' => 'scurt', 'beard' => true, 'glasses' => 'dreptunghi', 'frame' => '#053b5e', 'shirt' => '#f2a516', 'bg' => '#ffffff'],
        'copil' => ['skin' => '#f6d2b4', 'shade' => '#e9b893', 'hair' => '#b5652a', 'style' => 'copil', 'glasses' => 'rotund', 'frame' => '#e5484d', 'shirt' => '#2e9a6b', 'bg' => '#ffffff'],
        'senior' => ['skin' => '#efc4a3', 'shade' => '#dca885', 'hair' => '#c9ced3', 'style' => 'scurt', 'glasses' => 'dreptunghi', 'frame' => '#7a4e23', 'shirt' => '#6d5577', 'bg' => '#ffffff', 'book' => true],
        'fotocromatic' => ['skin' => '#e7b48f', 'shade' => '#d39a73', 'hair' => '#1f1a17', 'style' => 'mediu', 'glasses' => 'dreptunghi', 'frame' => '#1d2a33', 'shirt' => '#e5484d', 'bg' => '#ffffff', 'tint' => '#5b3a24'],
    ][$v] ?? [];
    $kid = $v === 'copil';
    $eyeY = $kid ? 200 : 190;
@endphp
<svg class="person person--{{ $v }}" viewBox="0 0 400 420" aria-hidden="true" focusable="false">
    {{-- părul din spate (lung / mediu) --}}
    @if ($p['style'] === 'lung')
        <path d="M108 178 C98 92 152 62 202 62 C258 62 308 96 296 182 L306 318 C286 340 262 330 252 304 L148 304 C138 332 112 340 94 318 Z" fill="{{ $p['hair'] }}"/>
    @elseif ($p['style'] === 'mediu')
        <path d="M114 186 C104 104 152 70 202 70 C256 70 302 104 290 188 C292 214 288 234 270 246 L130 246 C112 234 108 214 114 186 Z" fill="{{ $p['hair'] }}"/>
    @endif
    {{-- umerii și bluza --}}
    <path d="M52 420 C60 336 124 300 200 300 C276 300 340 336 348 420 Z" fill="{{ $p['shirt'] }}"/>
    <path d="M168 300 C176 322 224 322 232 300" fill="none" stroke="rgba(0,0,0,.12)" stroke-width="6" stroke-linecap="round"/>
    {{-- gâtul --}}
    <rect x="172" y="244" width="56" height="68" rx="24" fill="{{ $p['shade'] }}"/>
    {{-- urechile și capul --}}
    <ellipse cx="{{ $kid ? 118 : 123 }}" cy="{{ $eyeY + 6 }}" rx="13" ry="19" fill="{{ $p['shade'] }}"/>
    <ellipse cx="{{ $kid ? 282 : 277 }}" cy="{{ $eyeY + 6 }}" rx="13" ry="19" fill="{{ $p['shade'] }}"/>
    <ellipse cx="200" cy="{{ $kid ? 192 : 180 }}" rx="{{ $kid ? 84 : 78 }}" ry="{{ $kid ? 86 : 94 }}" fill="{{ $p['skin'] }}"/>
    {{-- obrajii --}}
    <circle cx="156" cy="{{ $eyeY + 34 }}" r="13" fill="#e8846b" opacity=".18"/>
    <circle cx="244" cy="{{ $eyeY + 34 }}" r="13" fill="#e8846b" opacity=".18"/>
    {{-- părul din față --}}
    @switch($p['style'])
        @case('lung')
            <path d="M120 168 C124 104 166 82 210 86 C254 90 284 118 282 170 C256 134 224 120 196 122 C164 124 140 140 120 168 Z" fill="{{ $p['hair'] }}"/>
            @break
        @case('mediu')
            <path d="M122 160 C120 104 162 82 206 84 C252 86 284 112 280 162 C262 136 236 128 214 130 C206 120 186 116 164 124 C146 132 132 144 122 160 Z" fill="{{ $p['hair'] }}"/>
            @break
        @case('copil')
            <path d="M116 176 C110 114 152 100 204 100 C256 100 296 120 286 178 C270 150 246 140 230 144 C214 132 186 132 170 142 C150 140 128 152 116 176 Z" fill="{{ $p['hair'] }}"/>
            @break
        @default
            <path d="M124 160 C118 98 160 80 206 82 C252 84 286 106 278 160 C266 132 248 118 208 116 C166 114 138 128 124 160 Z" fill="{{ $p['hair'] }}"/>
    @endswitch
    @if (! empty($p['beard']))
        <path d="M128 200 C130 262 166 290 200 290 C234 290 270 262 272 200 C262 238 238 254 200 254 C162 254 138 238 128 200 Z" fill="{{ $p['hair'] }}"/>
        <path d="M178 238 Q200 230 222 238" fill="none" stroke="{{ $p['hair'] }}" stroke-width="10" stroke-linecap="round"/>
    @endif
    {{-- sprâncenele, ochii, nasul, gura --}}
    <path d="M150 {{ $eyeY - 26 }} Q168 {{ $eyeY - 34 }} 184 {{ $eyeY - 26 }} M216 {{ $eyeY - 26 }} Q232 {{ $eyeY - 34 }} 250 {{ $eyeY - 26 }}" fill="none" stroke="{{ $p['style'] === 'scurt' && $v === 'senior' ? '#9aa1a7' : $p['hair'] }}" stroke-width="6" stroke-linecap="round"/>
    <circle cx="168" cy="{{ $eyeY }}" r="7" fill="#1d2329"/><circle cx="232" cy="{{ $eyeY }}" r="7" fill="#1d2329"/>
    <circle cx="170" cy="{{ $eyeY - 2 }}" r="2" fill="#fff"/><circle cx="234" cy="{{ $eyeY - 2 }}" r="2" fill="#fff"/>
    <path d="M200 {{ $eyeY + 8 }} Q192 {{ $eyeY + 28 }} 202 {{ $eyeY + 32 }}" fill="none" stroke="{{ $p['shade'] }}" stroke-width="5" stroke-linecap="round"/>
    @if (empty($p['beard']))
        <path d="M178 {{ $eyeY + 50 }} Q200 {{ $eyeY + 66 }} 222 {{ $eyeY + 50 }}" fill="none" stroke="#b5584a" stroke-width="6" stroke-linecap="round"/>
    @else
        <path d="M184 {{ $eyeY + 54 }} Q200 {{ $eyeY + 64 }} 216 {{ $eyeY + 54 }}" fill="none" stroke="#f3d6c4" stroke-width="5" stroke-linecap="round"/>
    @endif
    {{-- ochelarii --}}
    @php($lens = ! empty($p['tint']) ? $p['tint'] : '#cfe6f7')
    @php($lensOp = ! empty($p['tint']) ? '.82' : '.35')
    <g class="person__glasses" stroke="{{ $p['frame'] }}" stroke-width="7" stroke-linejoin="round">
        @switch($p['glasses'])
            @case('cateye')
                <path d="M132 {{ $eyeY - 18 }} L194 {{ $eyeY - 14 }} C196 {{ $eyeY + 10 }} 186 {{ $eyeY + 28 }} 164 {{ $eyeY + 28 }} C142 {{ $eyeY + 28 }} 134 {{ $eyeY + 10 }} 128 {{ $eyeY - 22 }} Z" fill="{{ $lens }}" fill-opacity="{{ $lensOp }}"/>
                <path d="M268 {{ $eyeY - 18 }} L206 {{ $eyeY - 14 }} C204 {{ $eyeY + 10 }} 214 {{ $eyeY + 28 }} 236 {{ $eyeY + 28 }} C258 {{ $eyeY + 28 }} 266 {{ $eyeY + 10 }} 272 {{ $eyeY - 22 }} Z" fill="{{ $lens }}" fill-opacity="{{ $lensOp }}"/>
                @break
            @case('rotund')
                <circle cx="166" cy="{{ $eyeY }}" r="30" fill="{{ $lens }}" fill-opacity="{{ $lensOp }}"/>
                <circle cx="234" cy="{{ $eyeY }}" r="30" fill="{{ $lens }}" fill-opacity="{{ $lensOp }}"/>
                @break
            @default
                <rect x="132" y="{{ $eyeY - 22 }}" width="62" height="46" rx="12" fill="{{ $lens }}" fill-opacity="{{ $lensOp }}"/>
                <rect x="206" y="{{ $eyeY - 22 }}" width="62" height="46" rx="12" fill="{{ $lens }}" fill-opacity="{{ $lensOp }}"/>
        @endswitch
        <path d="M194 {{ $eyeY - 6 }} Q200 {{ $eyeY - 12 }} 206 {{ $eyeY - 6 }}" fill="none"/>
        <path d="M132 {{ $eyeY - 10 }} L{{ $kid ? 112 : 118 }} {{ $eyeY - 4 }} M268 {{ $eyeY - 10 }} L{{ $kid ? 288 : 282 }} {{ $eyeY - 4 }}" fill="none"/>
    </g>
    {{-- reflexul de pe lentile --}}
    <path d="M146 {{ $eyeY - 10 }} l14 -6 M214 {{ $eyeY - 10 }} l14 -6" stroke="#fff" stroke-width="5" stroke-linecap="round" opacity=".7"/>
    @if (! empty($p['book']))
        {{-- cartea (citește prin zona pentru aproape a lentilei progresive) --}}
        <g transform="translate(120 330) rotate(-6)">
            <path d="M0 10 L80 0 L80 70 L0 80 Z" fill="#fff" stroke="#053b5e" stroke-width="5" stroke-linejoin="round"/>
            <path d="M80 0 L160 10 L160 80 L80 70 Z" fill="#fff" stroke="#053b5e" stroke-width="5" stroke-linejoin="round"/>
            <path d="M14 28 H66 M14 42 H62 M14 56 H66 M94 28 H146 M94 42 H140 M94 56 H146" stroke="#9fb1bf" stroke-width="4" stroke-linecap="round"/>
        </g>
    @endif
</svg>
