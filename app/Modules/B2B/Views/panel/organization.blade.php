@extends('layouts.panou')
@section('title', $org->name)
@php($fmt = fn ($v) => \App\Core\Support\Money::format($v))
@section('content')
    <x-panou.page-head :title="$org->name" :crumbs="['Parteneri' => route('panou.b2b.orgs'), $org->name => null]" :sub="(\App\Modules\B2B\Models\Organization::KINDS[$org->kind] ?? $org->kind).' · '.$org->status" />
    <div class="grid grid--aside">
        <div class="stack">
            <x-panou.panel title="Date">
                <form method="post" action="{{ route('panou.b2b.org.update', $org->id) }}" class="form-grid">@csrf
                    <x-panou.field name="nume" label="Denumire" :value="$org->name" required />
                    <x-panou.field name="tip" type="select" label="Tip" :options="\App\Modules\B2B\Models\Organization::KINDS" :value="$org->kind" :placeholder="false" />
                    <x-panou.field name="cui" label="CUI" :value="$org->vat_code" />
                    <x-panou.field name="reg_com" label="Nr. Reg. Com." :value="$org->registry_number" />
                    <x-panou.field name="adresa" label="Adresă de facturare" :value="$org->billing_address" :full="true" />
                    <x-panou.field name="telefon" label="Telefon" :value="$org->contact_phone" />
                    <x-panou.field name="email" type="email" label="Email" :value="$org->contact_email" />
                    <x-panou.field name="lista" type="select" label="Lista de preț" :options="$priceLists->all()" :value="$org->price_list_id" placeholder="fără (prețuri B2C)" />
                    <x-panou.field name="stare" type="select" label="Stare" :options="['aprobata' => 'Aprobat', 'suspendata' => 'Suspendat (fără acces)']" :value="$org->status === 'suspendata' ? 'suspendata' : 'aprobata'" :placeholder="false" />
                    <x-panou.field name="cu_tva" type="checkbox" label="Afișează prețurile cu TVA în portal" :value="$org->show_prices_with_vat" />
                    <x-panou.field name="note" type="textarea" label="Note interne" :value="$org->internal_notes" :full="true" />
                    <div class="form-actions">@can('b2b.approve')<button class="btn btn--primary" type="submit">Salvează</button>@endcan</div>
                </form>
            </x-panou.panel>
            <x-panou.panel title="Comenzi recente" :flush="true">
                <table class="table"><tbody>@forelse ($orders as $o)<tr><td><a href="{{ route('panou.orders.show', $o->uuid) }}">{{ $o->number }}</a></td><td>{{ $o->created_at->format('d.m.Y') }}</td><td>{{ $o->commercialLabel() }}</td><td>{{ $o->paymentLabel() }}</td><td class="num">{{ $fmt($o->total) }}</td></tr>@empty<tr><td class="muted">Nicio comandă.</td></tr>@endforelse</tbody></table>
            </x-panou.panel>
        </div>
        <div class="stack">
            <x-panou.panel title="Plată la termen">
                @if ($org->hasApprovedCredit())
                    <p>{{ $org->payment_terms_days }} zile · limită {{ $org->credit_limit !== null ? $fmt($org->credit_limit) : 'fără limită' }}<br><span class="small muted">aprobată {{ $org->terms_approved_at->format('d.m.Y') }}</span></p>
                    <p class="small">Sold deschis la termen: <strong>{{ $fmt($openBalance) }}</strong></p>
                @else
                    <p class="muted small">Nu este aprobată. Partenerul plătește prin transfer înainte de producție.</p>
                @endif
                @can('b2b.terms')
                <form method="post" action="{{ route('panou.b2b.org.terms', $org->id) }}" class="stack">@csrf
                    <x-panou.field name="zile" type="number" label="Zile" :value="$org->payment_terms_days" />
                    <x-panou.field name="limita" label="Limită de credit (lei, gol = fără limită)" :value="$org->credit_limit !== null ? number_format($org->credit_limit / 100, 2, ',', '') : ''" />
                    <button class="btn" type="submit">Aprobă condițiile</button>
                    @if ($org->hasApprovedCredit())<button class="btn btn--ghost" type="submit" name="retrage" value="1">Retrage plata la termen</button>@endif
                </form>
                @endcan
            </x-panou.panel>
            <x-panou.panel title="Utilizatori">
                @foreach ($org->members as $m)<p class="small">{{ $m->customer->fullName() }} · {{ $m->customer->email }}<br><span class="muted">{{ \App\Modules\B2B\Models\OrganizationMember::ROLES[$m->role] ?? $m->role }}{{ $m->accepted_at ? '' : ' · invitație neacceptată' }}</span></p>@endforeach
                @can('b2b.approve')
                <form method="post" action="{{ route('panou.b2b.org.invite', $org->id) }}" class="stack">@csrf
                    <x-panou.field name="email" type="email" label="Invită (email)" required />
                    <x-panou.field name="rol" type="select" label="Rol" :options="\App\Modules\B2B\Models\OrganizationMember::ROLES" value="admin_organizatie" :placeholder="false" />
                    <button class="btn btn--small" type="submit">Trimite invitația</button>
                </form>
                @endcan
            </x-panou.panel>
            <x-panou.panel title="Documente">
                @foreach ($documents as $d)<p class="small">{{ $d->title }} · {{ \App\Modules\B2B\Models\OrganizationDocument::KINDS[$d->kind] ?? $d->kind }} · {{ $d->created_at->format('d.m.Y') }}</p>@endforeach
                @can('b2b.documents')
                <form method="post" action="{{ route('panou.b2b.org.document', $org->id) }}" enctype="multipart/form-data" class="stack">@csrf
                    <x-panou.field name="titlu" label="Titlu" required />
                    <x-panou.field name="tip" type="select" label="Tip" :options="\App\Modules\B2B\Models\OrganizationDocument::KINDS" :placeholder="false" />
                    <x-panou.field name="comanda" label="Număr comandă (opțional)" />
                    <div class="field"><label class="field__label" for="fisier">Fișier PDF</label><input id="fisier" type="file" name="fisier" accept="application/pdf" required></div>
                    <button class="btn btn--small" type="submit">Încarcă</button>
                </form>
                @endcan
            </x-panou.panel>
        </div>
    </div>
@endsection
