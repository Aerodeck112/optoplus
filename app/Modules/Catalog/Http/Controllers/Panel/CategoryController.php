<?php

namespace App\Modules\Catalog\Http\Controllers\Panel;

use App\Core\Http\Crud\Field;
use App\Core\Http\Crud\ResourceController;
use App\Core\Support\Text;
use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CategoryController extends ResourceController
{
    protected function model(): string
    {
        return Category::class;
    }

    protected function routeBase(): string
    {
        return 'panou.catalog.categories';
    }

    protected function titles(): array
    {
        return ['plural' => 'Categorii', 'singular' => 'Categoria', 'new' => 'Categorie nouă'];
    }

    protected function description(): ?string
    {
        return 'Schimbarea căii publice nu creează automat redirect: adăugați-l în „Redirecturi”.';
    }

    protected function fields(): array
    {
        return [
            Field::make('name', 'Nume')->rules('required|string|max:160')->list()->searchable(),
            Field::make('path', 'Calea publică (ex. /rame/ochelari-de-vedere/)')->rules('required|string|max:255|regex:#^/[a-z0-9/-]*/$#')->list(),
            Field::make('kind', 'Tip', 'select')->options(['rame' => 'Rame', 'lentile' => 'Lentile', 'accesorii' => 'Accesorii'])->rules('required|in:rame,lentile,accesorii')->list(),
            Field::make('parent_id', 'Categoria părinte', 'select')->options(fn () => Category::orderBy('path')->pluck('path', 'id')->all())->rules('nullable|integer|exists:categories,id'),
            Field::make('is_visible', 'Vizibilă', 'checkbox')->list(),
            Field::make('sort', 'Ordine', 'number')->rules('nullable|integer|min:0|max:9999'),
            Field::make('intro', 'Introducere', 'textarea')->rules('nullable|string|max:3000')->full(),
            Field::make('seo_title', 'Titlu SEO')->rules('nullable|string|max:255'),
            Field::make('seo_description', 'Descriere SEO')->rules('nullable|string|max:320'),
        ];
    }

    protected function orderBy(Builder $query): Builder
    {
        return $query->orderBy('path');
    }

    protected function prepare(array $data, ?Model $model): array
    {
        $data['slug'] = Text::slug(basename(rtrim($data['path'], '/')) ?: $data['name']);
        $data['sort'] = (int) ($data['sort'] ?? 0);
        $data['intro'] = isset($data['intro']) ? strip_tags($data['intro']) : null;

        return $data;
    }
}
