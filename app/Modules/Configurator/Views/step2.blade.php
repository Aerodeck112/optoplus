@extends('configurator::partials.shell')
@php
    $fmt = fn (?int $v, bool $sign = true) => $v === null ? '' : ($sign && $v > 0 ? '+' : '').str_replace('.', ',', number_format($v / 100, 2, '.', ''));
    $mm = fn ($v) => $v === null ? '' : str_replace('.', ',', rtrim(rtrim(number_format($v / 10, 1, '.', ''), '0'), '.'));
    $m = (array) ($prescription?->measurements ?? []);
    $eyes = $input->eyes;
    $rows = array_filter(['od' => 'Ochiul drept (OD)', 'os' => 'Ochiul stâng (OS / OG)'], fn ($k) => $eyes === 'ambii' || $eyes === $k, ARRAY_FILTER_USE_KEY);
@endphp
@section('step')
<form method="post" action="/configurator/{{ $cfg->uuid }}/pas/2/" class="form" enctype="multipart/form-data" novalidate>
    @csrf
    <h1>Rețeta ta</h1>
    <p>Copiază valorile exact cum sunt scrise pe rețetă. Câmpurile goale înseamnă „nu apare pe rețetă”. <a href="/ghiduri/cum-citesti-reteta/" target="_blank" rel="noopener">Cum citesc rețeta?</a></p>
    <div class="table-wrap">
        <table class="rx-table">
            <thead><tr><th scope="col"><span class="sr-only">Ochi</span></th><th scope="col">SPH</th><th scope="col">CYL</th><th scope="col">AX (°)</th><th scope="col">ADD</th></tr></thead>
            <tbody>
            @foreach ($rows as $eye => $label)
                @php($rx = $input->{$eye})
                <tr>
                    <th scope="row">{{ $label }}</th>
                    <td data-label="SPH"><label class="sr-only" for="{{ $eye }}_sph">SPH {{ $label }}</label><input id="{{ $eye }}_sph" name="{{ $eye }}[sph]" inputmode="decimal" autocomplete="off" placeholder="ex. −2,25" value="{{ old($eye.'.sph', $fmt($rx?->sph)) }}" @error($eye.'.sph') aria-invalid="true" @enderror></td>
                    <td data-label="CYL"><label class="sr-only" for="{{ $eye }}_cyl">CYL {{ $label }}</label><input id="{{ $eye }}_cyl" name="{{ $eye }}[cyl]" inputmode="decimal" autocomplete="off" placeholder="—" value="{{ old($eye.'.cyl', $fmt($rx?->cyl)) }}" data-cyl="{{ $eye }}"></td>
                    <td data-label="AX"><label class="sr-only" for="{{ $eye }}_axis">AX {{ $label }}</label><input id="{{ $eye }}_axis" name="{{ $eye }}[axis]" inputmode="numeric" autocomplete="off" placeholder="—" value="{{ old($eye.'.axis', $rx?->axis) }}" data-axis="{{ $eye }}"></td>
                    <td data-label="ADD"><label class="sr-only" for="{{ $eye }}_add">ADD {{ $label }}</label><input id="{{ $eye }}_add" name="{{ $eye }}[add]" inputmode="decimal" autocomplete="off" placeholder="—" value="{{ old($eye.'.add', $fmt($rx?->add, false)) }}"></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="small muted" style="margin: 0">Cilindrul poate fi scris cu minus sau cu plus — îl păstrăm exact ca pe rețetă. AX se completează doar dacă există CYL. ADD apare de obicei doar la rețetele pentru toate distanțele.</p>

    <fieldset class="field" style="border: 0; padding: 0; margin: 8px 0 0">
        <legend class="label">Distanța pupilară (DP)</legend>
        @php($mode = old('dp_mod', $prescription?->pd_mode ?? 'binocular'))
        <div class="choice-grid choice-grid--2">
            <label class="choice"><input type="radio" name="dp_mod" value="binocular" @checked($mode === 'binocular') data-pd-mode><span class="choice__mark"></span><span class="choice__body"><span class="choice__title">O singură valoare</span><span class="choice__text">ex. 63</span></span></label>
            <label class="choice"><input type="radio" name="dp_mod" value="monocular" @checked($mode === 'monocular') data-pd-mode><span class="choice__mark"></span><span class="choice__body"><span class="choice__title">Câte una pe ochi</span><span class="choice__text">ex. 31,5 / 31,5</span></span></label>
        </div>
        <div class="form-grid form-grid--2" style="margin-top: 12px">
            <div class="field" data-pd="binocular"><label for="dp">DP (mm)</label><input id="dp" name="dp" inputmode="decimal" value="{{ old('dp', $mm($m['pd'] ?? null)) }}" placeholder="ex. 63"></div>
            <div class="field" data-pd="monocular"><label for="dp_od">DP ochiul drept (mm)</label><input id="dp_od" name="dp_od" inputmode="decimal" value="{{ old('dp_od', $mm($m['pd_od'] ?? null)) }}" placeholder="ex. 31,5"></div>
            <div class="field" data-pd="monocular"><label for="dp_os">DP ochiul stâng (mm)</label><input id="dp_os" name="dp_os" inputmode="decimal" value="{{ old('dp_os', $mm($m['pd_os'] ?? null)) }}" placeholder="ex. 31,5"></div>
        </div>
        <span class="hint">Lipsește de pe rețetă? <a href="/ghiduri/distanta-pupilara/" target="_blank" rel="noopener">Ce este DP</a> sau bifați mai jos să vă contactăm.</span>
    </fieldset>

    <details class="faq" @if(($m['height_od'] ?? null) || old('h_od')) open @endif>
        <summary>Înălțimea de montaj (necesară la lentilele progresive)<x-site.icon name="chevron-down" /></summary>
        <div class="form-grid form-grid--2">
            <div class="field"><label for="h_od">Ochiul drept (mm)</label><input id="h_od" name="h_od" inputmode="decimal" value="{{ old('h_od', $mm($m['height_od'] ?? null)) }}"></div>
            <div class="field"><label for="h_os">Ochiul stâng (mm)</label><input id="h_os" name="h_os" inputmode="decimal" value="{{ old('h_os', $mm($m['height_os'] ?? null)) }}"></div>
        </div>
        <p class="small muted">Se măsoară cu rama aleasă pe față. Dacă nu o aveți, bifați opțiunea de mai jos: comanda nu intră în producție până nu o confirmă specialistul.</p>
    </details>
    <label class="check"><input type="checkbox" name="masuratori_specialist" value="1" @checked(old('masuratori_specialist', $m['by_store'] ?? false))><span>Nu am toate măsurătorile (DP, înălțime). Vreau să fiu contactat de un specialist înainte de producție.</span></label>

    <div class="field">
        <label for="reteta">Încarcă rețeta (fotografie sau PDF) <span class="muted">— opțional, recomandat</span></label>
        <input id="reteta" type="file" name="reteta" accept="image/jpeg,image/png,image/webp,application/pdf">
        @if ($prescription && $prescription->files()->exists())<span class="hint"><x-site.icon name="check" :size="16" /> Ai încărcat deja {{ $prescription->files()->count() }} fișier(e). Specialistul compară valorile cu rețeta.</span>@endif
        <span class="hint">Fișierul este privat și îl vede doar personalul care îți verifică rețeta. Nu completăm automat valorile din fotografie.</span>
    </div>
    <label class="check"><input type="checkbox" name="acord_reteta" value="1" @checked(old('acord_reteta'))><span>Sunt de acord cu prelucrarea rețetei mele (date privind sănătatea) de către Optoplus, pentru realizarea lentilelor.</span></label>

    <div class="cfg__nav">
        <button class="btn btn--ghost" type="submit" name="actiune" value="inapoi" formnovalidate>Înapoi</button>
        <button class="btn btn--primary" type="submit">Continuă la lentile</button>
    </div>

    <details class="box" style="margin-top: 24px" @if($errors->has('nume') || $errors->has('telefon')) open @endif>
        <summary><strong>Nu înțeleg rețeta — vreau ajutorul unui specialist</strong></summary>
        <p style="margin-top: 12px">Încarcă rețeta mai sus și lasă-ne datele. Un specialist te sună și completează împreună cu tine configurația. Nu modificăm valorile rețetei.</p>
        <div class="form-grid form-grid--2">
            <div class="field"><label for="nume">Nume</label><input id="nume" name="nume" value="{{ old('nume', $cfg->customer?->fullName()) }}" autocomplete="name"></div>
            <div class="field"><label for="telefon">Telefon</label><input id="telefon" type="tel" name="telefon" value="{{ old('telefon', $cfg->customer?->phone) }}" autocomplete="tel"></div>
            <div class="field"><label for="email">Email <span class="muted">(opțional)</span></label><input id="email" type="email" name="email" value="{{ old('email', $cfg->customer?->email) }}" autocomplete="email"></div>
        </div>
        <button class="btn" type="submit" name="actiune" value="ajutor" formnovalidate style="margin-top: 12px">Trimite specialistului</button>
    </details>
</form>
@endsection
