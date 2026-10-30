@extends('layouts.site')
@section('title', $seo['title'] ?? $title)
@section('description', $seo['description'] ?? '')
@if (! empty($seo['noindex']))@section('robots', 'noindex, follow')@endif
@section('body_class', 'page-'.$kind)
@section('content')
    @if ($preview)
        <div class="wrap"><div class="notice notice--warn" role="status"><x-site.icon name="eye" /><p>Previzualizare: {{ $previewLabel }}. Această versiune nu este publică.</p></div></div>
    @endif
    @if ($kind !== 'home' && ! collect($blocks)->contains(fn ($b) => $b['type'] === 'hero_lens'))
        <div class="wrap">
            <x-site.breadcrumbs :items="$breadcrumbs" />
            <header class="page-head"><h1>{{ $title }}</h1></header>
        </div>
    @endif
    @foreach ($blocks as $i => $block)
        @includeIf('cms::blocks.'.$block['type'], ['d' => $block['data'], 'index' => $i, 'media' => $media, 'first' => $i === 0])
    @endforeach
@endsection
