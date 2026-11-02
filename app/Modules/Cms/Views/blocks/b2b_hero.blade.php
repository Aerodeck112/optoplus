{{-- Deschiderea paginii pentru parteneri: mesajul, butoanele și macheta animată a portalului (date fictive). --}}
<section class="b2b-hero">
    @if (! empty($breadcrumbs))<div class="wrap b2b-hero__crumbs"><x-site.breadcrumbs :items="$breadcrumbs" /></div>@endif
    <div class="wrap b2b-hero__grid">
        <div class="b2b-hero__text">
            @if (! empty($d['eyebrow']))<p class="eyebrow">{{ $d['eyebrow'] }}</p>@endif
            <h1>{{ $d['title'] ?? '' }}</h1>
            @if (! empty($d['subtitle']))<p class="lead">{{ $d['subtitle'] }}</p>@endif
            <div class="btn-row">
                @if (! empty($d['primary']['url']))<a class="btn btn--primary btn--lg" href="{{ $d['primary']['url'] }}">{{ $d['primary']['label'] }}</a>@endif
                @if (! empty($d['secondary']['url']))<a class="btn btn--lg" href="{{ $d['secondary']['url'] }}"><x-site.icon name="user" :size="18" />{{ $d['secondary']['label'] }}</a>@endif
            </div>
            @if (! empty($d['points']))
                <ul class="b2b-hero__points">
                    @foreach ($d['points'] as $p)@if (! empty($p['text']))<li><x-site.icon name="check" :size="16" />{{ $p['text'] }}</li>@endif @endforeach
                </ul>
            @endif
        </div>
        <figure class="b2b-hero__visual">
            @include('b2b::mock.screen', ['screen' => 'quick'])
            <div class="b2b-float b2b-float--track" aria-hidden="true">
                @include('b2b::mock.screen', ['screen' => 'tracking', 'chrome' => false])
            </div>
            <div class="b2b-float b2b-float--badge" aria-hidden="true"><x-site.icon name="shield" :size="20" /><span>Rețetă verificată<small>de specialistul optic</small></span></div>
            @if (! empty($d['caption']))<figcaption>{{ $d['caption'] }}</figcaption>@endif
        </figure>
    </div>
</section>
