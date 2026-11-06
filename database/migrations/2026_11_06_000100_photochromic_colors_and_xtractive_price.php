<?php

use App\Modules\Lenses\Support\PhotochromicColors;
use Illuminate\Database\Migrations\Migration;

/** 1.0.11: culorile Helio / Xtractive, prețul Xtractive (250 lei) și regula ramelor fără contur. Vezi PhotochromicColors. */
return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(PhotochromicColors::class)) {
            require_once dirname(__DIR__, 2).'/app/Modules/Lenses/Support/PhotochromicColors.php';
        }
        PhotochromicColors::run();
    }

    public function down(): void {}
};
