@extends('layouts.site')
@section('title', $seo['title'] ?? $title)
@section('description', $seo['description'] ?? '')
@if (! empty($seo['noindex']) || $preview)@section('robots', 'noindex, nofollow')@endif
@section('body_class', 'page-'.$kind)
@section('content')
    @if ($preview)
        <div class="wrap"><div class="notice notice--warn" role="status"><x-site.icon name="eye" /><p>Previzualizare: {{ $previewLabel }}. Această versiune nu este publică.</p></div></div>
    @endif
    @if ($kind !== 'home' && ! collect($blocks)->contains(fn ($b) => in_array($b['type'], ['hero_lens', 'b2b_hero'], true)))
        <div class="wrap">
            <x-site.breadcrumbs :items="$breadcrumbs" />
            <header class="page-head"><h1>{{ $title }}</h1></header>
        </div>
    @endif
    @php($legalRo = $kind === 'policy' && \App\Modules\I18n\Support\Locales::isTranslated(app()->getLocale()))
    @if ($legalRo)
        {{-- Textele juridice nu se traduc automat: rămân în română (versiunea valabilă). --}}
        <div class="wrap"><div class="notice notice--warn" role="note"><x-site.icon name="info" /><p>Acest text juridic este disponibil doar în limba română, care este versiunea valabilă.</p></div></div>
    @endif
    <div @if($legalRo) translate="no" lang="ro" @endif>
    @foreach ($blocks as $i => $block)
        @includeIf('cms::blocks.'.$block['type'], ['d' => $block['data'], 'index' => $i, 'media' => $media, 'first' => $i === 0, 'breadcrumbs' => $breadcrumbs ?? []])
    @endforeach
    </div>
@endsection
