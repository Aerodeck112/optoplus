<?php

namespace App\Modules\Cms\Http\Controllers;

use App\Core\Http\Crud\Field;
use App\Core\Http\Crud\ResourceController;
use App\Core\Support\Html;
use App\Modules\Cms\Models\FaqItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Întrebările frecvente: cele bifate „pentru asistent” devin sursă aprobată pentru răspunsurile asistentului. */
class FaqAdminController extends ResourceController
{
    protected function model(): string
    {
        return FaqItem::class;
    }

    protected function routeBase(): string
    {
        return 'panou.cms.faq';
    }

    protected function titles(): array
    {
        return ['plural' => 'Întrebări frecvente', 'singular' => 'Întrebarea', 'new' => 'Întrebare nouă'];
    }

    protected function description(): ?string
    {
        return 'Răspunsurile bifate „folosește în asistent” sunt singurele texte libere pe care asistentul le poate cita.';
    }

    protected function fields(): array
    {
        return [
            Field::make('question', 'Întrebarea')->rules('required|string|max:255')->list()->searchable()->full(),
            Field::make('topic', 'Subiect', 'select')->options(FaqItem::TOPICS)->rules('required|in:'.implode(',', array_keys(FaqItem::TOPICS)))->list(),
            Field::make('answer', 'Răspunsul (HTML simplu)', 'textarea')->rules('required|string|max:10000')->full(),
            Field::make('is_published', 'Publicată pe site', 'checkbox')->list(),
            Field::make('use_in_assistant', 'Folosește în asistent (sursă aprobată)', 'checkbox')->list(),
            Field::make('sort', 'Ordine', 'number')->rules('nullable|integer|min:0|max:9999'),
        ];
    }

    protected function orderBy(Builder $query): Builder
    {
        return $query->orderBy('topic')->orderBy('sort');
    }

    protected function prepare(array $data, ?Model $model): array
    {
        $data['answer'] = Html::rich((string) $data['answer']);
        $data['sort'] = (int) ($data['sort'] ?? 0);
        if (! empty($data['use_in_assistant']) && (! $model || ! $model->use_in_assistant)) {
            $data['approved_by'] = Auth::id();
            $data['approved_at'] = now();
        }

        return $data;
    }
}
