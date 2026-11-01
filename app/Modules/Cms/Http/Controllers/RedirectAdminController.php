<?php

namespace App\Modules\Cms\Http\Controllers;

use App\Core\Http\Crud\Field;
use App\Core\Http\Crud\ResourceController;
use App\Modules\Cms\Models\Redirect;
use App\Modules\Cms\Services\BlockRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Redirecturile 301 și răspunsurile 410 (pagină retrasă definitiv). Nu se redirecționează nimic spre prima pagină în masă. */
class RedirectAdminController extends ResourceController
{
    protected function model(): string
    {
        return Redirect::class;
    }

    protected function routeBase(): string
    {
        return 'panou.cms.redirects';
    }

    protected function titles(): array
    {
        return ['plural' => 'Redirecturi', 'singular' => 'Redirectul', 'new' => 'Redirect nou'];
    }

    protected function description(): ?string
    {
        return 'Adresele vechi ale siteului (importate) și cele adăugate manual. Vedeți și „Adrese negăsite” pentru 404-urile reale.';
    }

    protected function fields(): array
    {
        return [
            Field::make('source_path', 'Adresa veche (cale, ex. /produs/vechi/)')->rules('required|string|max:255')->list()->searchable(),
            Field::make('target', 'Destinația (gol pentru 410)')->rules('nullable|string|max:500')->list()->searchable(),
            Field::make('status_code', 'Cod', 'select')->options([301 => '301 — mutat definitiv', 302 => '302 — temporar', 410 => '410 — retras definitiv'])->rules('required|in:301,302,410')->list(),
            Field::make('is_active', 'Activ', 'checkbox')->list(),
            Field::make('note', 'Notă')->rules('nullable|string|max:255'),
        ];
    }

    protected function orderBy(Builder $query): Builder
    {
        return $query->orderByDesc('hits')->orderBy('source_path');
    }

    protected function prepare(array $data, ?Model $model): array
    {
        $data['source_path'] = Redirect::normalizePath($data['source_path']);
        $target = trim((string) ($data['target'] ?? ''));
        if ((int) $data['status_code'] !== 410 && ($target === '' || BlockRegistry::url($target) === '')) {
            throw ValidationException::withMessages(['target' => 'Pentru 301/302 completați o destinație locală (/…) sau https://.']);
        }
        if ($target === '/' && (int) $data['status_code'] === 301) {
            throw ValidationException::withMessages(['target' => 'Nu redirecționați spre prima pagină: alegeți pagina cea mai apropiată sau 410.']);
        }
        $data['target'] = (int) $data['status_code'] === 410 ? '' : $target;
        $data['created_by'] ??= Auth::id();

        return $data;
    }

    public function notFound()
    {
        return view('cms::panel.notfound', ['rows' => DB::table('cms_not_found_log')->orderByDesc('hits')->limit(200)->get()]);
    }
}
