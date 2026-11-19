{{-- Filtrele de pe site (gen, material, construcție, tip, formă): bifele decid în ce pagini și filtre apare produsul. --}}
<input type="hidden" name="atribute_trimise" value="1">
@foreach ($attributes as $code => [$label, $values])
    <fieldset class="field field--full perm-group" style="border:0;padding:0;margin:0">
        <legend class="field__label">{{ $label }}</legend>
        <div class="cluster">
            @foreach ($values as $id => $valueLabel)
                <label class="check"><input type="checkbox" name="atribute[]" value="{{ $id }}" @checked(in_array($id, $selected, true))><span>{{ $valueLabel }}</span></label>
            @endforeach
        </div>
    </fieldset>
@endforeach
