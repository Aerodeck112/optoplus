<?php

namespace App\Modules\Cms\Http\Controllers;

use App\Core\Http\Crud\Field;
use App\Core\Http\Crud\ResourceController;
use App\Modules\Cms\Models\MenuItem;
use App\Modules\Cms\Services\BlockRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MenuAdminController extends ResourceController
{
    protected function model(): string
    {
        return MenuItem::class;
    }

    protected function routeBase(): string
    {
        return 'panou.cms.menus';
    }

    protected function titles(): array
    {
        return ['plural' => 'Meniuri', 'singular' => 'Elementul de meniu', 'new' => 'Element nou'];
    }

    protected function fields(): array
    {
        return [
            Field::make('label', 'Text')->rules('required|string|max:160')->list()->searchable(),
            Field::make('menu', 'Meniul', 'select')->options(['header' => 'Antet', 'footer' => 'Subsol', 'footer_legal' => 'Subsol — juridic'])->rules('required|in:header,footer,footer_legal')->list(),
            Field::make('url', 'Link (ex. /rame/ sau https://…)')->rules('nullable|string|max:500')->list(),
            Field::make('parent_id', 'Sub elementul', 'select')->options(fn () => MenuItem::whereNull('parent_id')->orderBy('menu')->orderBy('sort')->get()->mapWithKeys(fn ($m) => [$m->id => $m->menu.' › '.$m->label])->all())->rules('nullable|integer|exists:cms_menu_items,id'),
            Field::make('description', 'Descriere scurtă (meniu extins)')->rules('nullable|string|max:255'),
            Field::make('sort', 'Ordine', 'number')->rules('nullable|integer|min:0|max:9999')->list(),
            Field::make('is_visible', 'Vizibil', 'checkbox')->list(),
            Field::make('new_tab', 'Se deschide în tab nou', 'checkbox'),
        ];
    }

    protected function orderBy(Builder $query): Builder
    {
        return $query->orderBy('menu')->orderByRaw('coalesce(parent_id, id)')->orderBy('sort');
    }

    protected function prepare(array $data, ?Model $model): array
    {
        if (($data['url'] ?? '') !== '' && BlockRegistry::url($data['url']) === '') {
            throw ValidationException::withMessages(['url' => 'Linkul trebuie să fie o cale locală (/…) sau o adresă https://.']);
        }
        $data['sort'] = (int) ($data['sort'] ?? 0);

        return $data;
    }
}
