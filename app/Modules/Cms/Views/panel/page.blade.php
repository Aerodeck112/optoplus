@extends('layouts.panou')
@section('title', $page->title)
@section('content')
    <x-panou.page-head :title="$page->title" :crumbs="['Pagini' => route('panou.cms.pages.index'), $page->title => null]" :sub="$page->path">
        <x-slot:actions>
            @if ($version)<a class="btn" href="{{ route('panou.cms.pages.preview', [$page->id, $version->id]) }}" target="_blank" rel="noopener">Previzualizează ciorna</a>@endif
            <a class="btn btn--ghost" href="{{ $page->path }}" target="_blank" rel="noopener">Pagina publică</a>
        </x-slot:actions>
    </x-panou.page-head>
    @if ($page->needs_legal_review)
        <div class="alert alert--warn"><strong>Text juridic de verificat.</strong> Ciorna a fost redactată pe baza datelor firmei din ANAF și a regulilor comerciale confirmate. Citiți-o integral (inclusiv mențiunile „DE VERIFICAT”), corectați-o dacă e cazul și abia apoi publicați-o.</div>
    @endif
    @if ($version && $page->published_version_id === $version->id)<div class="alert alert--ok">Versiunea afișată este cea publicată.</div>@elseif($page->published_version_id)<div class="alert alert--info">Editați o ciornă; pagina publică arată altă versiune până la publicare.</div>@endif

    <div class="grid grid--aside">
        <form method="post" action="{{ route('panou.cms.pages.save', $page->id) }}" class="stack">@csrf
            <input type="hidden" name="lock" value="{{ $page->lock_version }}">
            <x-panou.panel title="Titlu și SEO">
                <div class="form-grid">
                    <x-panou.field name="titlu" label="Titlul paginii" :value="$version?->title ?? $page->title" required :full="true" />
                    <x-panou.field name="seo_titlu" label="Titlu SEO (opțional)" :value="$version?->seo['title'] ?? null" />
                    <x-panou.field name="seo_descriere" label="Descriere SEO" :value="$version?->seo['description'] ?? null" />
                    <x-panou.field name="noindex" type="checkbox" label="Nu indexa pagina" :value="$version?->seo['noindex'] ?? false" />
                </div>
            </x-panou.panel>
            @foreach ((array) ($version?->blocks ?? []) as $i => $block)
                @php($def = $types[$block['type']] ?? null)
                @continue(! $def)
                <x-panou.panel :title="($i + 1).'. '.$def['label']">
                    <x-slot:actions>
                        <button class="btn btn--small btn--ghost" name="actiune" value="sus:{{ $i }}" @disabled($i === 0)>↑</button>
                        <button class="btn btn--small btn--ghost" name="actiune" value="jos:{{ $i }}" @disabled($loop->last)>↓</button>
                        <label class="check small"><input type="checkbox" name="blocks[{{ $i }}][_sterge]" value="1"><span>Șterge blocul</span></label>
                    </x-slot:actions>
                    <p class="small muted">{{ $def['description'] }}</p>
                    <input type="hidden" name="blocks[{{ $i }}][type]" value="{{ $block['type'] }}">
                    @include('cms::panel.block-fields', ['fields' => $def['fields'], 'values' => (array) ($block['data'] ?? []), 'prefix' => 'blocks['.$i.'][data]'])
                </x-panou.panel>
            @endforeach
            <x-panou.panel title="Salvare">
                <div class="form-grid">
                    <x-panou.field name="bloc_nou" type="select" label="Adaugă un bloc la final" :options="collect($types)->map(fn ($t) => $t['label'])->all()" placeholder="—" />
                    <x-panou.field name="nota" label="Notă pentru istoric (ce ați schimbat)" />
                </div>
                <x-slot:footer><button class="btn btn--primary" type="submit">Salvează ciorna</button></x-slot:footer>
            </x-panou.panel>
        </form>

        <div class="stack">
            @can('cms.publish')
            <x-panou.panel title="Publicare">
                @if ($page->kind === 'policy' && ! auth()->user()->can('cms.legal'))
                    <p class="small muted">Textele juridice se publică de cine are permisiunea „texte juridice”.</p>
                @else
                <form method="post" action="{{ route('panou.cms.pages.publish', $page->id) }}" class="stack">@csrf
                    <input type="hidden" name="versiune" value="{{ $version?->id }}">
                    @if ($page->kind === 'policy')<x-panou.field name="verificat" type="checkbox" label="Am citit și verificat integral textul juridic" />@endif
                    <button class="btn btn--primary" type="submit" @disabled(! $version)>Publică această versiune</button>
                </form>
                @endif
                @if ($page->status === 'published' && $page->kind !== 'home')
                    <form method="post" action="{{ route('panou.cms.pages.unpublish', $page->id) }}" style="margin-top:.75rem">@csrf<button class="btn btn--ghost btn--small" type="submit">Retrage pagina</button></form>
                @endif
            </x-panou.panel>
            @endcan
            <x-panou.panel title="Istoric" :flush="true">
                <table class="table small"><tbody>
                    @foreach ($versions as $v)
                        <tr @if($v->id === $version?->id) style="background:var(--lagoon-soft)" @endif>
                            <td><a href="{{ route('panou.cms.pages.edit', [$page->id, $v->id]) }}">v{{ $v->id }}</a>@if($v->id === $page->published_version_id) <x-panou.badge tone="ok">publicată</x-panou.badge>@endif<br><span class="muted">{{ $v->created_at->format('d.m.Y H:i') }}</span></td>
                            <td>{{ $v->note }}</td>
                            <td>@can('cms.publish')@if($v->id !== $page->current_version_id)<form method="post" action="{{ route('panou.cms.pages.restore', [$page->id, $v->id]) }}">@csrf<button class="link-btn">Restaurează</button></form>@endif @endcan</td>
                        </tr>
                    @endforeach
                </tbody></table>
            </x-panou.panel>
        </div>
    </div>
@endsection
