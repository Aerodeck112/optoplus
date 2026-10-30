<form class="form" method="post" action="/formular/parteneriat/" enctype="multipart/form-data" novalidate>
    @csrf
    @include('storefront::forms.errors')
    <div class="hp" aria-hidden="true" style="position:absolute;left:-9999px"><label>Nu completați<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="form-grid form-grid--2">
        <div class="field"><label for="p_company">Denumirea firmei</label><input id="p_company" name="company_name" value="{{ old('company_name') }}" required autocomplete="organization"></div>
        <div class="field"><label for="p_vat">CUI</label><input id="p_vat" name="vat_code" value="{{ old('vat_code') }}" required inputmode="text" placeholder="ex. RO12345678"></div>
        <div class="field"><label for="p_kind">Tipul activității</label>
            <select id="p_kind" name="kind" required>@foreach (\App\Modules\B2B\Models\Organization::KINDS as $k => $l)<option value="{{ $k }}" @selected(old('kind') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="p_city">Localitatea</label><input id="p_city" name="city" value="{{ old('city') }}" autocomplete="address-level2"></div>
        <div class="field"><label for="p_contact">Persoana de contact</label><input id="p_contact" name="contact_name" value="{{ old('contact_name') }}" required autocomplete="name"></div>
        <div class="field"><label for="p_email">Email</label><input id="p_email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></div>
        <div class="field"><label for="p_phone">Telefon</label><input id="p_phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel"></div>
    </div>
    <div class="field"><label for="p_message">Ce lentile comandați de obicei? <span class="muted">(opțional)</span></label><textarea id="p_message" name="message">{{ old('message') }}</textarea></div>
    <label class="check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))><span>Sunt de acord ca Optoplus să folosească aceste date pentru analiza cererii de parteneriat.</span></label>
    <div><button class="btn btn--primary" type="submit">Trimite cererea</button></div>
</form>
