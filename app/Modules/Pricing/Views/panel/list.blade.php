@extends('layouts.panou')
@section('title', $list->name)
@php($fmt = fn ($v) => \App\Core\Support\Money::format($v))
@section('content')
    <x-panou.page-head :title="$list->name" :crumbs="['Liste de preț' => route('panou.pricing.lists.index'), $list->name => null]" :sub="'Prețuri '.($list->prices_include_vat ? 'cu TVA' : 'fără TVA')" />
    <div class="grid grid--aside">
        <x-panou.panel :title="'Prețuri ('.$items->count().')'" :flush="true">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Articol</th><th>Cod</th><th>De la (buc.)</th><th class="num">Preț</th><th></th></tr></thead>
                <tbody>@forelse ($items as $i)
                    @php($v = $i->item_type === 'variant' ? $variants[$i->item_id] ?? null : null)
                    @php($o = $i->item_type === 'offering' ? $offerings[$i->item_id] ?? null : null)
                    <tr><td>{{ $v ? $v->product->name.($v->name ? ' — '.$v->name : '') : ($o ? $o->product->name.' '.$o->material->indexLabel() : $i->item_type.' #'.$i->item_id) }}</td>
                        <td><code>{{ $v?->sku ?? $o?->code }}</code></td><td>{{ $i->min_qty }}</td><td class="num">{{ $fmt($i->price) }}</td>
                        <td>@can('pricing.manage')<form method="post" action="{{ route('panou.pricing.lists.items.remove', [$list->id, $i->id]) }}">@csrf<button class="link-btn">Elimină</button></form>@endcan</td></tr>
                @empty<tr><td colspan="5" class="muted">Lista nu are prețuri proprii; se folosește regula de rezervă.</td></tr>@endforelse</tbody>
            </table></div>
        </x-panou.panel>
        @can('pricing.manage')
        <div class="stack">
            <x-panou.panel title="Adaugă sau modifică prețuri">
                <form method="post" action="{{ route('panou.pricing.lists.items', $list->id) }}" class="stack">@csrf
                    <x-panou.field name="randuri" type="textarea" label="Câte un rând: COD;PREȚ sau COD;PREȚ;CANTITATE_MINIMĂ" rows="8" placeholder="monofocal-standard-150;150,00&#10;monofocal-standard-150;130,00;20&#10;SKU-RAMA;180" required hint="COD = codul ofertei de lentilă (din „Lentile”) sau SKU-ul ramei. Un preț existent pentru același cod și prag se înlocuiește." />
                    <button class="btn btn--primary" type="submit">Salvează prețurile</button>
                </form>
            </x-panou.panel>
            <x-panou.panel title="Setările listei">@include('pricing::panel.list-form', ['list' => $list])</x-panou.panel>
        </div>
        @endcan
    </div>
@endsection
