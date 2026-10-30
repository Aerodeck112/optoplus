@if ($errors->any())
    <div class="notice notice--error" role="alert" tabindex="-1"><x-site.icon name="warn" /><div><p><strong>Verificați câmpurile marcate:</strong></p><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
@endif
