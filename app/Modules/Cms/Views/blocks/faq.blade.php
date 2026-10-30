@php($faqs = \App\Modules\Cms\Models\FaqItem::where('is_published', true)->when($d['topic'] ?? null, fn ($q, $t) => $q->where('topic', $t))->orderBy('sort')->get())
@if ($faqs->isNotEmpty())
<section class="section">
    <div class="wrap">
        @if (! empty($d['title']))<h2>{{ $d['title'] }}</h2>@endif
        <div class="faq prose">
            @foreach ($faqs as $faq)
                <details><summary>{{ $faq->question }}<x-site.icon name="chevron-down" /></summary><div>{!! $faq->answer !!}</div></details>
            @endforeach
        </div>
    </div>
</section>
@push('jsonld')
<script type="application/ld+json" nonce="{{ app('csp-nonce') }}">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqs->map(fn ($f) => ['@type' => 'Question', 'name' => $f->question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($f->answer)]])->values()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush
@endif
