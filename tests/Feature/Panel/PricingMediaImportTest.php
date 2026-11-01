<?php

namespace Tests\Feature\Panel;

use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Cms\Models\Media;
use App\Modules\Identity\Roles;
use App\Modules\Pricing\Models\Coupon;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\Promotion;
use App\Modules\Pricing\Services\PriceEngine;
use App\Modules\Pricing\Support\PriceLine;
use App\Modules\Pricing\Support\PricingContext;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsCatalog;
use Tests\TestCase;

/** Promoții, cupoane, liste de preț, bibliotecă media și import din panou. Date fictive. */
class PricingMediaImportTest extends TestCase
{
    use BuildsCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLenses();
        config(['optoplus.media_path' => storage_path('framework/testing/media')]);
    }

    public function test_promotion_with_coupon_is_applied_only_with_the_code(): void
    {
        $frame = $this->frame(30000);
        $this->actingAsRole(Roles::MANAGER);
        $this->post('/panou/preturi/promotii', ['nume' => 'Toamnă', 'aplicare' => 'frames', 'tip' => 'percent', 'valoare' => '10', 'prioritate' => 10, 'canal' => 'b2c', 'activa' => '1'])->assertSessionHasNoErrors();
        $p = Promotion::firstOrFail();
        $this->post('/panou/preturi/promotii/'.$p->id.'/cupoane', ['cod' => 'toamna10'])->assertSessionHasNoErrors();
        $this->assertSame('TOAMNA10', Coupon::first()->code);
        $engine = app(PriceEngine::class);
        $this->assertSame(30000, $engine->quote([PriceLine::product('a', $frame->id)], PricingContext::b2c())->totalGross);
        $this->assertSame(27000, $engine->quote([PriceLine::product('a', $frame->id)], PricingContext::b2c(['couponCode' => 'toamna10']))->totalGross);
    }

    public function test_invalid_promotion_values_are_rejected(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $this->post('/panou/preturi/promotii', ['nume' => 'x', 'aplicare' => 'order', 'tip' => 'percent', 'valoare' => '150', 'prioritate' => 1, 'canal' => 'b2c'])->assertSessionHasErrors('valoare');
        $this->post('/panou/preturi/promotii', ['nume' => 'x', 'aplicare' => 'brand', 'tip' => 'percent', 'valoare' => '5', 'prioritate' => 1, 'canal' => 'b2c'])->assertSessionHasErrors('aplicare');
        $this->post('/panou/preturi/promotii', ['nume' => 'x', 'aplicare' => 'order', 'tip' => 'fixed_price', 'valoare' => '5', 'prioritate' => 1, 'canal' => 'b2c'])->assertSessionHasErrors('tip');
    }

    public function test_price_list_bulk_add_by_code(): void
    {
        $frame = $this->frame(30000);
        $this->actingAsRole(Roles::MANAGER);
        $this->post('/panou/preturi/liste', ['nume' => 'Clinici', 'cod' => 'clinici', 'rezerva' => 'b2c_discount', 'reducere' => '10', 'activa' => '1'])->assertSessionHasNoErrors();
        $list = PriceList::firstOrFail();
        $this->post('/panou/preturi/liste/'.$list->id.'/preturi', ['randuri' => "monofocal-standard-150;150,00\nmonofocal-standard-150;130;20\n{$frame->sku};200\nNU-EXISTA;10"])
            ->assertSessionHasErrors('randuri');
        $this->assertSame(3, $list->items()->count());
        $this->get('/panou/preturi/liste/'.$list->id)->assertOk()->assertSee('150,00');
        $this->assertSame(1000, $list->fresh()->fallback_discount_bp);
    }

    public function test_media_upload_reencodes_and_rejects_svg(): void
    {
        $this->actingAsRole(Roles::EDITOR_SITE);
        $img = UploadedFile::fake()->image('laborator.jpg', 800, 600);
        $this->post('/panou/site/media', ['fisiere' => [$img], 'dosar' => 'productie', 'alt' => 'Laboratorul (fictiv)'])->assertSessionHasNoErrors();
        $m = Media::firstOrFail();
        $this->assertSame('productie', $m->folder);
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->post('/panou/site/media', ['fisiere' => [$svg], 'dosar' => 'pagini'])->assertSessionHasErrors();
        $this->assertSame(1, Media::count());
        $this->get('/panou/site/media')->assertOk()->assertSee('ID '.$m->id);
    }

    public function test_import_simulation_writes_nothing_and_failed_images_can_be_retried(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $before = \App\Modules\Catalog\Models\Product::count();
        $csv = file_get_contents(collect(glob(database_path('content/woocommerce/wc-product-export-*.csv')))->first());
        $this->post('/panou/import/simulare', ['fisier' => UploadedFile::fake()->createWithContent('export.csv', $csv)])->assertRedirect();
        $this->assertSame($before, \App\Modules\Catalog\Models\Product::count());
        $this->get('/panou/import')->assertOk();
        $frame = $this->frame(1000);
        ProductImage::create(['product_id' => $frame->product_id, 'source_url' => 'https://optoplus.ro/x.jpg', 'status' => 'failed']);
        $this->post('/panou/import/imagini/reincearca')->assertSessionHas('status');
        $this->assertSame(1, ProductImage::where('status', 'pending')->count());
    }

    public function test_content_editor_cannot_change_prices_or_import(): void
    {
        $this->actingAsRole(Roles::EDITOR_SITE);
        $this->post('/panou/preturi/promotii', [])->assertForbidden();
        $this->get('/panou/import')->assertForbidden();
    }
}
