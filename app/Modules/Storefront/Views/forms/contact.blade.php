<form class="form" method="post" action="/formular/contact/" novalidate>
    @csrf
    @include('storefront::forms.errors')
    <div class="hp" aria-hidden="true" style="position:absolute;left:-9999px"><label>Nu completați<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="form-grid form-grid--2">
        <div class="field"><label for="c_name">Nume</label><input id="c_name" name="name" value="{{ old('name') }}" required autocomplete="name"></div>
        <div class="field"><label for="c_email">Email</label><input id="c_email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></div>
        <div class="field"><label for="c_phone">Telefon <span class="muted">(opțional)</span></label><input id="c_phone" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel"></div>
        <div class="field"><label for="c_subject">Subiect</label><input id="c_subject" name="subject" value="{{ old('subject', request('subiect')) }}" required></div>
    </div>
    <div class="field"><label for="c_message">Mesaj</label><textarea id="c_message" name="message" required>{{ old('message') }}</textarea>
        <span class="hint">Nu trimiteți valori din rețetă prin acest formular; pentru rețete folosiți configuratorul.</span></div>
    <label class="check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))><span>Sunt de acord ca Optoplus să folosească aceste date pentru a-mi răspunde (<a href="/politica-de-confidentialitate/">confidențialitate</a>).</span></label>
    <div><button class="btn btn--primary" type="submit">Trimite mesajul</button></div>
</form>
