<form method="post" action="{{ $list ? route('panou.pricing.lists.update', $list->id) : route('panou.pricing.lists.store') }}" class="form-grid">@csrf
    <x-panou.field name="nume" label="Nume" :value="$list?->name" required />
    <x-panou.field name="cod" label="Cod (fără spații)" :value="$list?->code" required />
    <x-panou.field name="rezerva" type="select" label="Pentru articolele fără preț în listă" :options="['b2c_discount' => 'Preț B2C cu reducere', 'b2c' => 'Preț B2C', 'none' => 'Fără preț (cerere de ofertă)']" :value="$list?->fallback ?? 'b2c_discount'" :placeholder="false" />
    <x-panou.field name="reducere" label="Reducerea de rezervă (%)" :value="$list ? $list->fallback_discount_bp / 100 : ''" />
    <x-panou.field name="cu_tva" type="checkbox" label="Prețurile din listă includ TVA" :value="$list?->prices_include_vat" />
    <x-panou.field name="activa" type="checkbox" label="Activă" :value="$list?->is_active ?? true" />
    <div class="form-actions"><button class="btn btn--primary" type="submit">Salvează</button></div>
</form>
