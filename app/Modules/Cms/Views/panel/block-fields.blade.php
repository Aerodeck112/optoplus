{{-- Câmpurile unui bloc (recursiv pentru grupuri și liste). $fields = definiția, $values = datele, $prefix = numele HTML. --}}
<div class="form-grid">
@foreach ($fields as $name => $def)
    @php($n = $prefix.'['.$name.']')
    @php($v = $values[$name] ?? null)
    @switch($def['type'])
        @case('group')
            <fieldset class="field--full block-group"><legend>{{ $def['label'] }}</legend>
                @include('cms::panel.block-fields', ['fields' => $def['fields'], 'values' => (array) $v, 'prefix' => $n])
            </fieldset>
            @break
        @case('list')
            <fieldset class="field--full block-group"><legend>{{ $def['label'] }}</legend>
                @php($items = array_values((array) $v))
                @foreach (array_merge($items, [[], []]) as $i => $item)
                    <div class="block-item"><span class="small muted">{{ $i < count($items) ? '#'.($i + 1) : 'rând nou (opțional)' }}</span>
                        @include('cms::panel.block-fields', ['fields' => $def['fields'], 'values' => (array) $item, 'prefix' => $n.'['.$i.']'])
                    </div>
                @endforeach
                <p class="small muted">Pentru a elimina un element, goliți-i câmpurile.</p>
            </fieldset>
            @break
        @case('rich')<x-panou.field :name="$n" type="textarea" :label="$def['label'].' (HTML simplu: p, h2, h3, ul, ol, li, a, strong, em, tabel)'" :value="$v" :full="true" rows="10" />@break
        @case('textarea')<x-panou.field :name="$n" type="textarea" :label="$def['label']" :value="$v" :full="true" />@break
        @case('select')<x-panou.field :name="$n" type="select" :label="$def['label']" :options="$def['options']" :value="$v" :placeholder="false" />@break
        @case('checkbox')<x-panou.field :name="$n" type="checkbox" :label="$def['label']" :value="$v" />@break
        @case('number')<x-panou.field :name="$n" type="number" :label="$def['label']" :value="$v" />@break
        @case('media')<x-panou.field :name="$n" type="number" :label="$def['label'].' (ID din biblioteca media)'" :value="$v" />@break
        @case('reusable')<x-panou.field :name="$n" type="select" :label="$def['label']" :options="$reusable" :value="$v" />@break
        @default<x-panou.field :name="$n" :label="$def['label']" :value="$v" />
    @endswitch
@endforeach
</div>
