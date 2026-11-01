@extends('layouts.panou')
@section('title', $p->exists ? $p->name : 'Promoție nouă')
@php($val = $p->exists ? ($p->type === 'percent' ? rtrim(rtrim(number_format($p->value / 100, 2, ',', ''), '0'), ',') : number_format($p->value / 100, 2, ',', '')) : '')
@section('content')
    <x-panou.page-head :title="$p->exists ? $p->name : 'Promoție nouă'" :crumbs="['Promoții' => route('panou.pricing.promotions.index'), ($p->exists ? $p->name : 'nouă') => null]" />
    <div class="grid grid--aside">
        <x-panou.panel>
            <form method="post" action="{{ $p->exists ? route('panou.pricing.promotions.update', $p->id) : route('panou.pricing.promotions.store') }}" class="form-grid">@csrf
                <x-panou.field name="nume" label="Nume intern" :value="$p->name" required />
                <x-panou.field name="eticheta" label="Textul pentru client (ex. „Reducere de toamnă”)" :value="$p->customer_label" />
                <x-panou.field name="aplicare" type="select" label="Se aplică la" :options="\App\Modules\Pricing\Models\Promotion::SCOPES" :value="$p->scope" :placeholder="false" />
                <x-panou.field name="canal" type="select" label="Canal" :options="['b2c' => 'Clienți (B2C)', 'b2b' => 'Parteneri (B2B)', 'all' => 'Ambele']" :value="$p->channel" :placeholder="false" />
                <div class="field field--full"><span class="field__label">Categorii (dacă se aplică la categorii)</span><div class="cluster">@foreach ($categories as $id => $path)<label class="check small"><input type="checkbox" name="categorii[]" value="{{ $id }}" @checked(in_array($id, (array) $p->scope_ids) && $p->scope === 'category')><span>{{ $path }}</span></label>@endforeach</div></div>
                <div class="field field--full"><span class="field__label">Mărci (dacă se aplică la mărci)</span><div class="cluster">@foreach ($brands as $id => $name)<label class="check small"><input type="checkbox" name="marci[]" value="{{ $id }}" @checked(in_array($id, (array) $p->scope_ids) && $p->scope === 'brand')><span>{{ $name }}</span></label>@endforeach</div></div>
                <x-panou.field name="produse" type="textarea" label="Produse (dacă se aplică la produse): câte un SKU, slug sau link pe rând" :value="$productSlugs" :full="true" />
                <x-panou.field name="tip" type="select" label="Tip reducere" :options="\App\Modules\Pricing\Models\Promotion::TYPES" :value="$p->type" :placeholder="false" />
                <x-panou.field name="valoare" label="Valoare (% sau lei)" :value="$val" required hint="Procent: 10 = 10%. Sumă fixă / preț fix: în lei." />
                <x-panou.field name="de_la" type="date" label="De la" :value="$p->starts_at?->format('Y-m-d')" />
                <x-panou.field name="pana_la" type="date" label="Până la (inclusiv)" :value="$p->ends_at?->format('Y-m-d')" />
                <x-panou.field name="minim" label="Valoare minimă a comenzii (lei)" :value="$p->min_order_total ? number_format($p->min_order_total / 100, 2, ',', '') : ''" />
                <x-panou.field name="prioritate" type="number" label="Prioritate (mic = întâi)" :value="$p->priority" required />
                <x-panou.field name="max_utilizari" type="number" label="Utilizări maxime în total" :value="$p->max_uses" />
                <x-panou.field name="max_per_client" type="number" label="Utilizări maxime per client" :value="$p->max_uses_per_customer" />
                <x-panou.field name="beneficiari" label="Categoria de beneficiari (ex. pensionari)" :value="$p->beneficiary_category" />
                <x-panou.field name="dovada" type="checkbox" label="Cere dovada eligibilității (verificată de vânzări)" :value="$p->requires_proof" />
                <x-panou.field name="cumulabila" type="checkbox" label="Se cumulează cu alte promoții cumulabile" :value="$p->stackable" />
                <x-panou.field name="cu_cupon" type="checkbox" label="Se aplică doar cu cupon" :value="$p->requires_coupon" />
                <x-panou.field name="activa" type="checkbox" label="Activă" :value="$p->is_active" />
                <div class="form-actions">@can('pricing.manage')<button class="btn btn--primary" type="submit">Salvează</button>@endcan</div>
            </form>
        </x-panou.panel>
        @if ($p->exists)
        <x-panou.panel title="Cupoane">
            @foreach ($p->coupons as $c)
                <div class="cluster" style="justify-content:space-between"><span><code>{{ $c->code }}</code> · {{ $c->uses_count }}@if($c->max_uses)/{{ $c->max_uses }}@endif utilizări @if($c->expires_at)· expiră {{ $c->expires_at->format('d.m.Y') }}@endif</span>
                    @can('pricing.manage')<form method="post" action="{{ route('panou.pricing.promotions.coupon.toggle', [$p->id, $c->id]) }}">@csrf<button class="btn btn--small {{ $c->is_active ? '' : 'btn--ghost' }}">{{ $c->is_active ? 'Dezactivează' : 'Activează' }}</button></form>@endcan</div>
            @endforeach
            @can('pricing.manage')
            <form method="post" action="{{ route('panou.pricing.promotions.coupon', $p->id) }}" class="stack" style="margin-top:1rem">@csrf
                <x-panou.field name="cod" label="Cod nou" required />
                <x-panou.field name="max" type="number" label="Utilizări maxime" />
                <x-panou.field name="expira" type="date" label="Expiră la" />
                <button class="btn" type="submit">Adaugă cuponul</button>
            </form>
            @endcan
        </x-panou.panel>
        @endif
    </div>
@endsection
