{{-- Video YouTube: imagine de previzualizare + buton; iframe-ul (youtube-nocookie) se încarcă la apăsare sau, cu
     „pornire automată”, când blocul ajunge pe ecran, fără sunet și în buclă. Fără JavaScript: link spre YouTube. --}}
@php($id = \App\Modules\Cms\Services\BlockRegistry::youtubeId((string) ($d['youtube_url'] ?? '')))
@if ($id)
<section class="section section--video">
    <div class="wrap video-block">
        @if (! empty($d['title']) || ! empty($d['text']))
            <div class="video-block__text" data-reveal>
                @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
                @if (! empty($d['text']))<p class="lead">{{ $d['text'] }}</p>@endif
            </div>
        @endif
        <figure class="video" data-module="video" data-video-id="{{ $id }}" @if(! empty($d['autoplay'])) data-autoplay @endif data-reveal>
            <a class="video__poster" href="https://www.youtube.com/watch?v={{ $id }}" target="_blank" rel="noopener" aria-label="Pornește videoclipul{{ ! empty($d['title']) ? ': '.$d['title'] : '' }}">
                <img src="https://i.ytimg.com/vi/{{ $id }}/maxresdefault.jpg" alt="" loading="lazy" width="1280" height="720">
                <span class="video__play" aria-hidden="true"><svg width="34" height="34" viewBox="0 0 24 24"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg></span>
            </a>
            @if (! empty($d['caption']))<figcaption>{{ $d['caption'] }}</figcaption>@endif
        </figure>
    </div>
</section>
@endif
