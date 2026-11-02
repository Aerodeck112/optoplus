{{-- Turul interactiv al portalului: file (taburi) în stânga, macheta animată a ecranului în dreapta.
     Fără JavaScript, toate ecranele se văd unul sub altul. --}}
@php($tabs = array_values(array_filter($d['tabs'] ?? [], fn ($t) => ! empty($t['title']))))
@if ($tabs)
<section class="section section--tour">
    <div class="wrap">
        @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
        @if (! empty($d['intro']))<p class="lead">{{ $d['intro'] }}</p>@endif
        <div class="tour" data-module="b2b-tour">
            <div class="tour__tabs" role="tablist" aria-label="{{ $d['title'] ?? 'Portalul partenerilor' }}">
                @foreach ($tabs as $i => $t)
                    <button class="tour__tab" type="button" role="tab" id="tur-tab-{{ $i }}" aria-controls="tur-ecran-{{ $i }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" @if($i) tabindex="-1" @endif>
                        <span class="tour__num">{{ $i + 1 }}</span>
                        <span><strong>{{ $t['title'] }}</strong>@if (! empty($t['text']))<span class="tour__text">{{ $t['text'] }}</span>@endif</span>
                        <i class="tour__progress" aria-hidden="true"></i>
                    </button>
                @endforeach
            </div>
            <div class="tour__screens">
                @foreach ($tabs as $i => $t)
                    <div class="tour__screen" role="tabpanel" id="tur-ecran-{{ $i }}" aria-labelledby="tur-tab-{{ $i }}" @if($i) data-inactive @endif>
                        @if (! empty($t['text']))<p class="tour__mobile-text">{{ $t['text'] }}</p>@endif
                        @include('b2b::mock.screen', ['screen' => $t['screen'] ?: 'quick'])
                    </div>
                @endforeach
                @if (! empty($d['caption']))<p class="tour__caption">{{ $d['caption'] }}</p>@endif
            </div>
        </div>
    </div>
</section>
@endif
