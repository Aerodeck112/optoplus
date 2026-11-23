@extends('layouts.panou')
@section('title', 'Produse')
@section('content')
    <x-panou.page-head title="Produse" sub="Rame și accesorii. Lentilele produse în laborator se administrează în „Lentile”. Stări: publicat = pe site · ciornă = încă nepublicat · retras = scos de pe site, dar păstrat în panou (se poate republica sau, dacă nu are comenzi, șterge definitiv din pagina lui).">
        <x-slot:actions>@can('catalog.edit')<a class="btn btn--primary" href="{{ route('panou.catalog.product.create') }}"><x-panou.icon name="plus" />Ramă nouă</a>@endcan</x-slot:actions>
    </x-panou.page-head>
    <x-panou.panel :flush="true">
        <form class="filters" method="get" role="search">
            <x-panou.field name="q" type="search" label="Nume sau SKU" :value="$filters['q'] ?? ''" />
            <x-panou.field name="status" type="select" label="Stare" :options="['published' => 'Publicat', 'draft' => 'Ciornă', 'archived' => 'Retras']" :value="$filters['status'] ?? ''" placeholder="toate" />
            <x-panou.field name="brand_id" type="select" label="Marcă" :options="$brands->all()" :value="$filters['brand_id'] ?? ''" placeholder="toate" />
            <x-panou.field name="fara_dimensiuni" type="checkbox" label="Fără dimensiuni" :value="$filters['fara_dimensiuni'] ?? false" />
            <button class="btn" type="submit">Filtrează</button>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th></th><th>Produs</th><th>Marcă</th><th>Stare</th><th>Variante</th><th class="num">Preț</th><th>Stoc</th><th><span class="sr-only">Acțiuni</span></th></tr></thead>
            <tbody>@foreach ($products as $p)
                @php($img = $p->images->first()?->media)
                <tr>
                    <td style="width:56px">@if($img)<img src="{{ $img->bestUrl(320) }}" alt="" width="48" height="40" style="object-fit:contain">@endif</td>
                    <td><a class="row-link" href="{{ route('panou.catalog.product', $p->id) }}">{{ $p->name }}</a></td>
                    <td>{{ $p->brand?->name }}</td>
                    <td><x-panou.badge :tone="['published' => 'ok', 'draft' => 'warn', 'archived' => 'danger'][$p->status] ?? null">{{ ['published' => 'publicat', 'draft' => 'ciornă', 'archived' => 'retras'][$p->status] ?? $p->status }}</x-panou.badge></td>
                    <td>{{ $p->variants->count() }}</td>
                    <td class="num">{{ \App\Core\Support\Money::format($p->variants->min('price')) }}</td>
                    <td>{{ $p->variants->every(fn ($v) => $v->stock_qty === null) ? 'negestionat' : $p->variants->sum('stock_qty') }}</td>
                    <td class="row-actions"><div class="row-actions__inner">
                        <a class="btn btn--small" href="{{ route('panou.catalog.product', $p->id) }}">Editează</a>
                        <a class="btn btn--small btn--ghost" href="{{ $p->url() }}" target="_blank" rel="noopener">{{ $p->status === 'published' ? 'Vezi pe site' : 'Previzualizare' }}</a>
                    </div></td>
                </tr>
            @endforeach</tbody>
        </table></div>
        <x-slot:footer>{{ $products->links() }}</x-slot:footer>
    </x-panou.panel>
@endsection
