<?php

use App\Modules\Lenses\Support\PhotochromicLineup;
use Illuminate\Database\Migrations\Migration;

/**
 * 1.0.16: gama de lentile fotocromatice (Transitions, Xtractive, PhotoFast, Heliomatic), fiecare pe indicii ei,
 * și regula corectată pentru ramele fără contur (găurire): fără lentile 1,50 / 1,56, fotocromaticele sunt permise.
 * Clasa nouă se încarcă explicit: în timpul actualizării, în memorie este codul versiunii vechi (vezi CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(PhotochromicLineup::class)) {
            require_once dirname(__DIR__, 2).'/app/Modules/Lenses/Support/PhotochromicLineup.php';
        }
        PhotochromicLineup::run();
    }

    public function down(): void
    {
        // Datele rămân: tratamentele și rândurile noi pot fi deja în comenzi.
    }
};
