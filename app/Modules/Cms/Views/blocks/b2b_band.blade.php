{{-- Banda pentru parteneri: card luminos cu beneficiile, butoanele și urmărirea animată a unei lucrări
     (machetă cu date fictive). --}}
<section class="section section--b2b">
    <div class="wrap">
        <div class="b2b-card">
            <div class="b2b-card__text">
                <p class="eyebrow"><x-site.icon name="building" :size="16" />Portal pentru parteneri</p>
                @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
                @if (! empty($d['text']))<p class="lead">{{ $d['text'] }}</p>@endif
                @if (! empty($d['bullets']))
                    <ul class="b2b-card__bullets">@foreach ($d['bullets'] as $b)@if (! empty($b['text']))<li><x-site.icon name="check" :size="16" />{{ $b['text'] }}</li>@endif @endforeach</ul>
                @endif
                <div class="btn-row">
                    @if (! empty($d['primary']['url']))<a class="btn btn--primary" href="{{ $d['primary']['url'] }}">{{ $d['primary']['label'] }}</a>@endif
                    @if (! empty($d['secondary']['url']))<a class="btn" href="{{ $d['secondary']['url'] }}">{{ $d['secondary']['label'] }}</a>@endif
                </div>
            </div>
            <div class="b2b-card__visual">
                @include('b2b::mock.screen', ['screen' => 'tracking'])
                <div class="b2b-float b2b-float--badge" aria-hidden="true"><x-site.icon name="lab" :size="20" /><span>Direct din laborator<small>fără intermediari pentru lentile</small></span></div>
            </div>
        </div>
    </div>
</section>
