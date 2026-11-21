<?php

namespace App\Modules\Catalog\Http\Controllers\Panel;

use App\Core\Http\Crud\Field;
use App\Core\Http\Crud\ResourceController;
use App\Core\Support\Text;
use App\Modules\Catalog\Models\Brand;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BrandController extends ResourceController
{
    protected function model(): string
    {
        return Brand::class;
    }

    protected function routeBase(): string
    {
        return 'panou.catalog.brands';
    }

    protected function titles(): array
    {
        return ['plural' => 'Mărci', 'singular' => 'Marca', 'new' => 'Marcă nouă'];
    }

    protected function description(): ?string
    {
        return '„Marcă proprie” înseamnă că Optoplus vinde rama sub numele său; nu înseamnă că o produce. Datele producătorului apar pe pagina fiecărei rame a mărcii (obligatoriu pentru vânzarea online, Regulamentul (UE) 2023/988): completați-le din documentele furnizorului.';
    }

    protected function fields(): array
    {
        return [
            Field::make('name', 'Nume')->rules('required|string|max:120')->list()->searchable(),
            Field::make('slug', 'Slug (URL /marci/<slug>/)')->rules('nullable|string|max:140|alpha_dash')->list(),
            Field::make('is_own_brand', 'Marcă proprie', 'checkbox')->list(),
            Field::make('description', 'Descriere', 'textarea')->rules('nullable|string|max:5000')->full(),
            // 1.0.22 — Regulamentul (UE) 2023/988 (GPSR), art. 19: în oferta online apar producătorul (nume, adresă poștală
            // și electronică) și, pentru producătorii din afara UE, persoana responsabilă din UE.
            Field::make('manufacturer_name', 'Producător (denumirea completă, ex. firma care fabrică rama)')->rules('nullable|string|max:190'),
            Field::make('manufacturer_address', 'Adresa poștală a producătorului')->rules('nullable|string|max:255'),
            Field::make('manufacturer_contact', 'Contact electronic al producătorului (email sau site)')->rules('nullable|string|max:190'),
            Field::make('eu_responsible', 'Persoana responsabilă din UE (doar pentru producătorii din afara UE: nume, adresă, contact)')->rules('nullable|string|max:255')->full(),
            Field::make('seo_title', 'Titlu SEO')->rules('nullable|string|max:255'),
            Field::make('seo_description', 'Descriere SEO')->rules('nullable|string|max:320'),
        ];
    }

    protected function orderBy(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    protected function prepare(array $data, ?Model $model): array
    {
        $data['slug'] = ($data['slug'] ?? '') !== '' ? $data['slug'] : Text::slug($data['name']);
        $data['description'] = isset($data['description']) ? strip_tags($data['description']) : null;

        return $data;
    }
}
