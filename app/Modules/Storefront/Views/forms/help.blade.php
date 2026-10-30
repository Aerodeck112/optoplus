<form class="form" method="post" action="/formular/ajutor/" enctype="multipart/form-data" novalidate>
    @csrf
    @include('storefront::forms.errors')
    <div class="hp" aria-hidden="true" style="position:absolute;left:-9999px"><label>Nu completați<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="form-grid form-grid--2">
        <div class="field"><label for="h_name">Nume</label><input id="h_name" name="name" value="{{ old('name') }}" required autocomplete="name"></div>
        <div class="field"><label for="h_phone">Telefon</label><input id="h_phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel"></div>
        <div class="field"><label for="h_email">Email</label><input id="h_email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></div>
    </div>
    <fieldset class="field" style="border: 0; padding: 0; margin: 0">
        <legend class="label">Pentru ce folosiți ochelarii cel mai des?</legend>
        <div class="choice-grid choice-grid--2">
            @foreach (\App\Modules\Configurator\Support\ConfigurationInput::USAGES as $k => $l)
                <label class="choice"><input type="radio" name="usage" value="{{ $k }}" @checked(old('usage', 'nu_stiu') === $k)><span class="choice__mark"></span><span class="choice__body"><span class="choice__title">{{ $l }}</span></span></label>
            @endforeach
        </div>
    </fieldset>
    <div class="field"><label for="h_file">Fotografia sau PDF-ul rețetei <span class="muted">(opțional)</span></label><input id="h_file" type="file" name="reteta" accept="image/jpeg,image/png,image/webp,application/pdf">
        <span class="hint">JPG, PNG, WebP sau PDF, maximum 10 MB. Fișierul este privat: îl vede doar specialistul care vă contactează.</span></div>
    <div class="field"><label for="h_message">Ce ar trebui să știm? <span class="muted">(opțional)</span></label><textarea id="h_message" name="message" placeholder="ex. lucrez mult la calculator, vreau lentile mai subțiri">{{ old('message') }}</textarea></div>
    <label class="check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))><span>Sunt de acord ca Optoplus să folosească aceste date pentru a mă contacta cu o recomandare.</span></label>
    <label class="check"><input type="checkbox" name="consent_rx" value="1" @checked(old('consent_rx'))><span>Dacă am încărcat rețeta: sunt de acord cu prelucrarea ei (date privind sănătatea) de către specialistul Optoplus, numai pentru această cerere.</span></label>
    <div><button class="btn btn--primary" type="submit">Cere recomandarea</button></div>
</form>
