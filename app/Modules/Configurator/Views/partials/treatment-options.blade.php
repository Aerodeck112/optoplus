{{-- Culorile unui tratament (ex. fotocromatic): se văd mereu; alegerea unei culori bifează și tratamentul. --}}
@if ($t->requires_option && ! $why)
    @php($opts = $t->options->filter(fn ($op) => $op->is_active && $ot->allowsOption($op->code))->sortBy('sort'))
    <fieldset class="swatches" data-option-for="{{ $t->id }}">
        <legend>Alege culoarea</legend>
        @foreach ($opts as $op)
            <label class="swatch"><input type="radio" name="optiune[{{ $t->id }}]" value="{{ $op->code }}" @checked(($chosen[$t->id]['option'] ?? null) === $op->code) data-calc><span class="swatch__dot" aria-hidden="true" style="background: {{ $op->swatch ?: '#8a9399' }}"></span><span>{{ $op->label }}</span></label>
        @endforeach
    </fieldset>
@endif
