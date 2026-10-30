<?php

namespace App\Modules\Orders\Services;

use Illuminate\Support\Facades\DB;

/** Numere de comandă consecutive pe an (OP-2026-00001), alocate sub blocare în tranzacția comenzii. */
class OrderNumber
{
    public function next(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        DB::table('order_number_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0]);
        $row = DB::table('order_number_sequences')->where('year', $year)->lockForUpdate()->first();
        $n = (int) $row->last_number + 1;
        DB::table('order_number_sequences')->where('year', $year)->update(['last_number' => $n]);

        return sprintf('OP-%d-%05d', $year, $n);
    }
}
