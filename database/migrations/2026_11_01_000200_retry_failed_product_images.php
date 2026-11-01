<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * După înlocuirea WordPress-ului pe același domeniu, descărcarea imaginilor de la optoplus.ro/wp-content/uploads
 * eșuează (404). Imaginile eșuate se repun la coadă: cronul le ia din pachetul de imagini (shared/wp-uploads).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_images')->where('status', 'failed')->update(['status' => 'pending', 'updated_at' => now()]);
    }

    public function down(): void {}
};
