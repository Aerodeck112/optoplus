<?php

use App\Modules\Lenses\Support\PhotochromicSplit;
use Illuminate\Database\Migrations\Migration;

/** 1.0.10: fotocromatic Helio / Xtractive și regulile pentru ramele fără ramă (găurire). Vezi PhotochromicSplit. */
return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(PhotochromicSplit::class)) {
            require_once dirname(__DIR__, 2).'/app/Modules/Lenses/Support/PhotochromicSplit.php';
        }
        PhotochromicSplit::run();
    }

    public function down(): void {}
};
