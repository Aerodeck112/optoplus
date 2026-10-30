<?php

namespace App\Modules\Cms\Services;

use App\Core\Support\Html;

/**
 * Tipurile de blocuri din care se compun paginile. Fiecare tip are câmpuri declarate (folosite de editorul din
 * panou și de sanitizare) și o vizualizare `cms::blocks.<tip>`. Editorul nu poate introduce cod: textele „rich”
 * trec prin HTMLPurifier, URL-urile acceptă doar căi locale sau http(s)/mailto/tel.
 */
final class BlockRegistry
{
    /** @return array<string, array{label:string, description:string, fields: array<string, array>}> */
    public static function types(): array
    {
        $link = ['label' => ['type' => 'text', 'label' => 'Text buton'], 'url' => ['type' => 'url', 'label' => 'Link']];

        return [
            'hero_lens' => [
                'label' => 'Deschidere cu lentila adnotată',
                'description' => 'Titlul paginii, butoanele principale, intrarea pentru parteneri și fotografia lentilei cu marcaje explicate.',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Titlu', 'required' => true],
                    'subtitle' => ['type' => 'textarea', 'label' => 'Text de sub titlu'],
                    'primary' => ['type' => 'group', 'label' => 'Butonul principal', 'fields' => $link],
                    'secondary' => ['type' => 'group', 'label' => 'Butonul secundar', 'fields' => $link],
                    'b2b_text' => ['type' => 'text', 'label' => 'Rândul pentru parteneri'],
                    'b2b_link' => ['type' => 'group', 'label' => 'Linkul pentru parteneri', 'fields' => $link],
                    'image' => ['type' => 'media', 'label' => 'Fotografia lentilei'],
                    'caption' => ['type' => 'text', 'label' => 'Legenda fotografiei'],
                    'pins' => ['type' => 'list', 'label' => 'Adnotări pe fotografie', 'fields' => [
                        'x' => ['type' => 'number', 'label' => 'Poziție orizontală (%)'],
                        'y' => ['type' => 'number', 'label' => 'Poziție verticală (%)'],
                        'title' => ['type' => 'text', 'label' => 'Titlu'],
                        'text' => ['type' => 'textarea', 'label' => 'Explicație'],
                    ]],
                ],
            ],
            'steps' => [
                'label' => 'Pași (secvență)',
                'description' => 'O succesiune reală de pași, numerotați automat.',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Titlu'],
                    'intro' => ['type' => 'textarea', 'label' => 'Introducere'],
                    'items' => ['type' => 'list', 'label' => 'Pași', 'fields' => [
                        'title' => ['type' => 'text', 'label' => 'Titlu'], 'text' => ['type' => 'textarea', 'label' => 'Text'],
                    ]],
                ],
            ],
            'lens_families' => [
                'label' => 'Familiile de lentile',
                'description' => 'Lista familiilor de lentile din catalog, cu schema zonelor și prețul „de la”, calculat automat.',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Titlu'],
                    'intro' => ['type' => 'textarea', 'label' => 'Introducere'],
                ],
            ],
            'production_story' => [
                'label' => 'Din laborator până la tine',
                'description' => 'Text despre producție, o comparație foto (ex. fotocromatic interior/exterior) și fotografii reale.',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Titlu'],
                    'text' => ['type' => 'rich', 'label' => 'Text'],
                    'compare_a' => ['type' => 'media', 'label' => 'Comparație — imaginea A'],
                    'compare_a_caption' => ['type' => 'text', 'label' => 'Legenda A'],
                    'compare_b' => ['type' => 'media', 'label' => 'Comparație — imaginea B'],
                    'compare_b_caption' => ['type' => 'text', 'label' => 'Legenda B'],
                    'compare_note' => ['type' => 'text', 'label' => 'Notă (ex. „fotografii ilustrative”)'],
                    'cta' => ['type' => 'group', 'label' => 'Link', 'fields' => $link],
                ],
            ],
            'photo_row' => [
                'label' => 'Rând de fotografii',
                'description' => 'Fotografii reale cu legendă.',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Titlu'],
                    'intro' => ['type' => 'textarea', 'label' => 'Introducere'],
                    'items' => ['type' => 'list', 'label' => 'Fotografii', 'fields' => [
                        'media' => ['type' => 'media', 'label' => 'Imagine'], 'caption' => ['type' => 'text', 'label' => 'Legendă'],
                    ]],
                ],
            ],
            'products' => [
                'label' => 'Selecție de produse',
                'description' => 'Rame din catalog (cele mai noi sau o marcă), cu prețul calculat de motorul de preț.',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Titlu'],
                    'intro' => ['type' => 'textarea', 'label' => 'Introducere'],
                    'brand' => ['type' => 'text', 'label' => 'Marca (slug, opțional)'],
                    'limit' => ['type' => 'number', 'label' => 'Număr de produse'],
                    'cta' => ['type' => 'group', 'label' => 'Link', 'fields' => $link],
                ],
            ],
            'b2b_band' => [
                'label' => 'Bandă pentru parteneri (B2B)',
                'description' => 'Bandă bleumarin cu beneficiile pentru clinici și magazine de optică.',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Titlu'],
                    'text' => ['type' => 'textarea', 'label' => 'Text'],
                    'bullets' => ['type' => 'list', 'label' => 'Puncte', 'fields' => ['text' => ['type' => 'text', 'label' => 'Text']]],
                    'primary' => ['type' => 'group', 'label' => 'Butonul principal', 'fields' => $link],
                    'secondary' => ['type' => 'group', 'label' => 'Butonul secundar', 'fields' => $link],
                ],
            ],
            'rich_text' => [
                'label' => 'Text',
                'description' => 'Paragrafe, subtitluri, liste.',
                'fields' => ['title' => ['type' => 'text', 'label' => 'Titlu'], 'html' => ['type' => 'rich', 'label' => 'Conținut']],
            ],
            'faq' => [
                'label' => 'Întrebări frecvente',
                'description' => 'Întrebările publicate dintr-un subiect.',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Titlu'],
                    'topic' => ['type' => 'select', 'label' => 'Subiect', 'options' => ['' => 'Toate'] + \App\Modules\Cms\Models\FaqItem::TOPICS],
                ],
            ],
            'cta' => [
                'label' => 'Îndemn la acțiune',
                'description' => 'Titlu scurt, text și un buton.',
                'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Titlu'], 'text' => ['type' => 'textarea', 'label' => 'Text'],
                    'primary' => ['type' => 'group', 'label' => 'Buton', 'fields' => $link],
                    'secondary' => ['type' => 'group', 'label' => 'Al doilea buton', 'fields' => $link],
                ],
            ],
            'contact' => [
                'label' => 'Date de contact și formular',
                'description' => 'Telefon, email, adresă (din Setări site) și formularul de contact.',
                'fields' => ['title' => ['type' => 'text', 'label' => 'Titlu'], 'text' => ['type' => 'textarea', 'label' => 'Text']],
            ],
            'partner_form' => [
                'label' => 'Formular de parteneriat B2B',
                'description' => 'Cererea de parteneriat, validată manual în panou.',
                'fields' => ['title' => ['type' => 'text', 'label' => 'Titlu'], 'text' => ['type' => 'textarea', 'label' => 'Text']],
            ],
            'help_form' => [
                'label' => 'Formular „Am nevoie de ajutor”',
                'description' => 'Cerere de recomandare: specialistul contactează clientul.',
                'fields' => ['title' => ['type' => 'text', 'label' => 'Titlu'], 'text' => ['type' => 'textarea', 'label' => 'Text']],
            ],
            'guides_list' => [
                'label' => 'Lista ghidurilor',
                'description' => 'Toate ghidurile publicate, cu titlu și descriere.',
                'fields' => ['title' => ['type' => 'text', 'label' => 'Titlu']],
            ],
            'reusable' => [
                'label' => 'Bloc refolosibil',
                'description' => 'Include un bloc salvat o singură dată și folosit pe mai multe pagini.',
                'fields' => ['block_id' => ['type' => 'reusable', 'label' => 'Blocul']],
            ],
        ];
    }

    public static function exists(string $type): bool
    {
        return array_key_exists($type, self::types());
    }

    /**
     * Curăță datele unui bloc după definiția câmpurilor: elimină câmpurile necunoscute, sanitizează HTML-ul,
     * acceptă doar URL-uri sigure.
     *
     * @return array{type:string, data:array}|null
     */
    public static function sanitize(array $block): ?array
    {
        $type = (string) ($block['type'] ?? '');
        if (! self::exists($type)) {
            return null;
        }

        return ['type' => $type, 'data' => self::clean(self::types()[$type]['fields'], (array) ($block['data'] ?? []))];
    }

    private static function clean(array $fields, array $data): array
    {
        $out = [];
        foreach ($fields as $name => $def) {
            $value = $data[$name] ?? null;
            $out[$name] = match ($def['type']) {
                'text' => mb_substr(trim(strip_tags((string) $value)), 0, 300),
                'textarea' => mb_substr(trim(strip_tags((string) $value)), 0, 2000),
                'rich' => Html::rich((string) $value),
                'url' => self::url((string) $value),
                'number' => is_numeric($value) ? (int) $value : null,
                'media', 'reusable' => is_numeric($value) ? (int) $value : null,
                'select' => array_key_exists((string) $value, $def['options']) ? (string) $value : array_key_first($def['options']),
                'checkbox' => (bool) $value,
                'group' => self::clean($def['fields'], (array) $value),
                'list' => array_values(array_map(fn ($item) => self::clean($def['fields'], (array) $item), array_filter((array) $value, 'is_array'))),
                default => null,
            };
        }

        return $out;
    }

    public static function url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return mb_substr($url, 0, 500);
        }

        return preg_match('#^(https?://|mailto:|tel:)#i', $url) ? mb_substr($url, 0, 500) : '';
    }
}
