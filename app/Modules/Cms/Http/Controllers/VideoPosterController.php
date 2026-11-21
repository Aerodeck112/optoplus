<?php

namespace App\Modules\Cms\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * 1.0.22: imaginea de previzualizare a unui videoclip YouTube, servită de pe siteul nostru (descărcată o singură dată
 * de pe i.ytimg.com și păstrată pe server). Astfel browserul vizitatorului nu contactează Google până nu pornește
 * videoclipul.
 */
class VideoPosterController extends Controller
{
    public function __invoke(string $id)
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{11}$/', $id), 404);
        $path = storage_path('app/video-posters/'.$id.'.jpg');
        if (! is_file($path)) {
            @mkdir(dirname($path), 0775, true);
            $bytes = null;
            foreach (['maxresdefault', 'hqdefault'] as $size) {
                try {
                    $response = Http::timeout(10)->connectTimeout(5)->get("https://i.ytimg.com/vi/{$id}/{$size}.jpg");
                    if ($response->successful() && str_starts_with((string) $response->header('Content-Type'), 'image/')) {
                        $bytes = $response->body();
                        break;
                    }
                } catch (Throwable) {
                    // încercăm dimensiunea următoare
                }
            }
            abort_if($bytes === null, 404);
            file_put_contents($path, $bytes);
        }

        return response()->file($path, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=2592000']);
    }
}
