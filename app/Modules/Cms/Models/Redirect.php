<?php

namespace App\Modules\Cms\Models;

use Illuminate\Database\Eloquent\Model;

/** Redirect individual (301/302) sau marcaj „410 Gone” pentru un URL vechi. */
class Redirect extends Model
{
    protected $table = 'cms_redirects';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_hit_at' => 'datetime'];
    }

    /** Normalizează calea: fără domeniu, cu „/” la început și la sfârșit (dacă nu are extensie), fără query. */
    public static function normalizePath(string $url): string
    {
        $path = parse_url(trim($url), PHP_URL_PATH) ?: '/';
        $path = '/'.ltrim(rawurldecode($path), '/');
        if (! str_ends_with($path, '/') && ! preg_match('/\.[a-z0-9]{2,5}$/i', $path)) {
            $path .= '/';
        }

        return mb_strtolower($path);
    }
}
