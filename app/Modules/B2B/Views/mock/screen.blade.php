{{-- Machetă animată a portalului pentru parteneri (pur decorativă, date evident fictive: coduri PAC-…, fără
     prețuri). Textul echivalent stă lângă ea, în pagină; machetele sunt ascunse cititoarelor de ecran. --}}
@php($screen ??= 'quick')
@php($chrome ??= true)
<div class="pm pm--{{ $screen }}" aria-hidden="true">
    @if ($chrome)<div class="pm__bar"><i></i><i></i><i></i><span>optoplus.ro/portal</span></div>@endif
    <div class="pm__body">
    @switch($screen)
        @case('quick')
            <div class="pm-head"><strong>Comandă rapidă</strong><span class="pm-chip">3 lucrări</span></div>
            <div class="pm-table">
                <div class="pm-row pm-row--h"><span>Cod pacient</span><span>Lentilă</span><span>OD / OS</span><span></span></div>
                <div class="pm-row" style="--i:0"><span>PAC-0142</span><span>Monofocală, antireflex</span><span>−1,25 / −1,50</span><span class="pm-ok"><x-site.icon name="check" :size="14" /></span></div>
                <div class="pm-row" style="--i:1"><span>PAC-0143</span><span>Progresivă</span><span>+1,00 / +0,75</span><span class="pm-ok"><x-site.icon name="check" :size="14" /></span></div>
                <div class="pm-row" style="--i:2"><span>PAC-0144</span><span>Degresivă (birou)</span><span>+2,00 / +2,25</span><span class="pm-ok"><x-site.icon name="check" :size="14" /></span></div>
            </div>
            <div class="pm-foot"><span class="pm-note">Validare și preț pe loc, la fiecare rând</span><span class="pm-btn">Adaugă în coș</span></div>
            @break
        @case('csv')
            <div class="pm-head"><strong>Import din fișier</strong><span class="pm-chip">model CSV</span></div>
            <div class="pm-drop"><span class="pm-file"><x-site.icon name="file" :size="18" />comenzi-saptamana-45.csv</span></div>
            <div class="pm-report">
                <p class="pm-report__ok"><x-site.icon name="check" :size="16" />18 rânduri valide, gata de comandă</p>
                <p class="pm-report__warn"><x-site.icon name="warn" :size="16" />Rândul 7: lipsește axa cilindrului (OS)</p>
            </div>
            @break
        @case('tracking')
            <div class="pm-head"><strong>Lucrarea PAC-0142</strong><span class="pm-chip pm-chip--live">în lucru</span></div>
            <ol class="pm-track">
                @foreach (['Comandă primită', 'Rețetă verificată de specialist', 'Lentilele sunt în producție', 'Control de calitate', 'Pregătită', 'Expediată'] as $i => $stage)
                    <li style="--i:{{ $i }}">{{ $stage }}</li>
                @endforeach
            </ol>
            @break
        @case('quotes')
            <div class="pm-head"><strong>Cerere de ofertă</strong><span class="pm-chip">versiunea 2</span></div>
            <ul class="pm-versions">
                <li><span>v1</span>Ofertă trimisă</li>
                <li class="is-current"><span>v2</span>Actualizată la cererea dumneavoastră</li>
            </ul>
            <div class="pm-foot"><span class="pm-note">Istoricul versiunilor rămâne în cont</span><span class="pm-btn">Acceptă oferta</span></div>
            @break
        @case('documents')
            <div class="pm-head"><strong>Documente</strong><span class="pm-chip">organizația dvs.</span></div>
            <ul class="pm-docs">
                @foreach (['Contract de colaborare', 'Factură', 'Ofertă acceptată', 'Certificat'] as $i => $doc)
                    <li style="--i:{{ $i }}"><x-site.icon name="file" :size="18" /><span>{{ $doc }}</span><em>PDF</em></li>
                @endforeach
            </ul>
            @break
        @case('team')
            <div class="pm-head"><strong>Utilizatori</strong><span class="pm-btn pm-btn--sm">Invită un coleg</span></div>
            <ul class="pm-team">
                <li><b>DF</b><span>Dana Fictiv<small>Administrator</small></span></li>
                <li><b>MT</b><span>Mihai Test<small>Comenzi și oferte</small></span></li>
                <li><b>AE</b><span>Ana Exemplu<small>Documente financiare</small></span></li>
                <li class="pm-team__new"><b>+</b><span>coleg@cabinet-fictiv.invalid<small>Invitație trimisă</small></span></li>
            </ul>
            @break
    @endswitch
    </div>
</div>
