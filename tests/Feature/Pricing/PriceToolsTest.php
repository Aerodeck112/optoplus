<?php

namespace Tests\Feature\Pricing;

use App\Core\Audit\AuditLog;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Identity\Roles;
use App\Modules\Pricing\Models\Promotion;
use App\Modules\Pricing\Services\PriceTools;
use App\Modules\Pricing\Services\ReducedPrice;
use App\Modules\Pricing\Support\PricingContext;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsCatalog;
use Tests\TestCase;

/** 1.0.27: Prețuri → Gestionare prețuri (în masă, cu anulare), reduceri datate și prețul anterior din ultimele 30 de zile. */
class PriceToolsTest extends TestCase
{
    use BuildsCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CatalogBaseSeeder::class);
    }

    public function test_rounding_rules(): void
    {
        $t = app(PriceTools::class);
        $this->assertSame(34900, $t->round(34873, 'leu'));
        $this->assertSame(34890, $t->round(34873, '90'));
        $this->assertSame(34899, $t->round(34873, '99'));
        $this->assertSame(34900, $t->round(34420, '9'));
        $this->assertSame(35000, $t->round(34873, '5'));
        $this->assertSame(35000, $t->round(34873, '10'));
        $this->assertSame(34873, $t->round(34873, 'nu'));
    }

    public function test_bulk_increase_by_brand_with_preview_apply_and_revert(): void
    {
        $brand = Brand::create(['name' => 'Marcă Fictivă', 'slug' => 'marca-fictiva-pret']);
        $a = $this->frame(30000);
        $a->product->update(['brand_id' => $brand->id]);
        $b = $this->frame(20000); // altă marcă: nu se atinge

        $this->actingAsRole(Roles::MANAGER);
        $params = ['tinta' => 'marci', 'marci' => [$brand->id], 'mod' => 'baza', 'operatie' => 'plus_proc', 'valoare' => '10', 'rotunjire' => '9'];
        $this->get('/panou/preturi/gestionare?'.http_build_query($params + ['previzualizare' => 1]))->assertOk()
            ->assertSee('1 variante')->assertSee('300,00 lei')->assertSee('329,00 lei', false);
        $this->assertSame(30000, (int) $a->fresh()->price, 'previzualizarea nu schimbă nimic');

        $this->post('/panou/preturi/gestionare', $params)->assertRedirect();
        $this->assertSame(32900, (int) $a->fresh()->price);
        $this->assertSame(20000, (int) $b->fresh()->price);
        $this->assertTrue(AuditLog::where('action', 'pricing.bulk_price_change')->exists());
        $this->assertSame(32900, (int) DB::table('variant_price_history')->where('variant_id', $a->id)->latest('id')->value('price'), 'istoricul prețurilor');

        $batch = DB::table('price_change_batches')->latest('id')->first();
        $this->post('/panou/preturi/gestionare/'.$batch->id.'/anuleaza')->assertRedirect();
        $this->assertSame(30000, (int) $a->fresh()->price);
        $this->assertNotNull(DB::table('price_change_batches')->find($batch->id)->reverted_at);

        // validare: procent prea mare la scădere
        $this->post('/panou/preturi/gestionare', ['tinta' => 'rame', 'mod' => 'baza', 'operatie' => 'minus_proc', 'valoare' => '95'])->assertSessionHasErrors('valoare');
        // fără dreptul „Prețuri”: interzis
        $this->actingAsRole(Roles::EDITOR_SITE);
        $this->get('/panou/preturi/gestionare')->assertForbidden();
    }

    public function test_dated_discount_shows_previous_price_only_with_30_days_of_history(): void
    {
        $a = $this->frame(30000);
        $this->actingAsRole(Roles::MANAGER);
        $this->post('/panou/preturi/gestionare', ['tinta' => 'produse', 'produse' => $a->sku, 'mod' => 'reducere', 'tip_reducere' => 'percent',
            'valoare_reducere' => '20', 'de_la' => now()->toDateString(), 'pana_la' => now()->addDays(7)->toDateString(), 'eticheta' => 'Reducere de iarnă fictivă'])->assertRedirect();
        $promo = Promotion::where('source', 'Gestionare prețuri')->firstOrFail();
        $this->assertSame([$a->product_id], $promo->scope_ids);
        $this->assertTrue($promo->is_active);

        // reducerea se aplică…
        $ctx = new PricingContext;
        $this->assertSame(24000, app(\App\Modules\Pricing\Services\PriceEngine::class)->displayPrice($a, $ctx));
        // …dar fără 30 de zile de istoric nu afișăm „prețul anterior”
        $this->assertNull(app(ReducedPrice::class)->forVariant($a, $ctx));
        auth()->logout();
        $this->get($a->product->url())->assertOk()->assertSee('240,00 lei')->assertDontSee('class="price-was"', false);

        // cu istoric de 40 de zile: prețul anterior = cel mai mic din ultimele 30 de zile
        DB::table('variant_price_history')->where('variant_id', $a->id)->delete();
        DB::table('variant_price_history')->insert([
            ['variant_id' => $a->id, 'price' => 32000, 'valid_from' => now()->subDays(40), 'source' => 'test'],
            ['variant_id' => $a->id, 'price' => 30000, 'valid_from' => now()->subDays(10), 'source' => 'test'],
        ]);
        $this->assertSame(['was' => 30000, 'now' => 24000, 'percent' => 20], app(ReducedPrice::class)->forVariant($a, $ctx));
        $this->get($a->product->url())->assertOk()->assertSee('class="price-was"', false)->assertSee('−20%');

        // o „promoție” fără dată de sfârșit nu afișează preț tăiat
        $promo->update(['ends_at' => null]);
        $this->assertNull(app(ReducedPrice::class)->forVariant($a, $ctx));
    }
}
