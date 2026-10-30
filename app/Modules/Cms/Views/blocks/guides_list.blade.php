@php($guides = \App\Modules\Cms\Models\Page::published()->where('kind', 'guide')->with('publishedVersion')->orderBy('sort')->orderBy('title')->get())
<section class="section" style="padding-top: 8px">
    <div class="wrap">
        @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
        <div class="entry-grid">
            @foreach ($guides as $g)
                <a class="entry" href="{{ $g->path }}"><x-site.icon name="file" /><strong>{{ $g->title }}</strong><span>{{ $g->publishedVersion?->seo['description'] ?? '' }}</span></a>
            @endforeach
        </div>
    </div>
</section>
