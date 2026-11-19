<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Cms\Models\Media;
use App\Modules\Identity\Roles;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsCatalog;
use Tests\TestCase;

/** 1.0.20: rame noi din panou (ciornă), variante, poze, filtre, copiere. Date fictive. */
class ProductEditorTest extends TestCase
{
    use BuildsCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CatalogBaseSeeder::class);
        config(['optoplus.media_path' => storage_path('framework/testing/media')]);
    }

    private function valueId(string $attribute, string $label): int
    {
        return (int) Attribute::where('code', $attribute)->firstOrFail()->values()->where('label', $label)->value('id');
    }

    public function test_manager_creates_a_frame_as_draft_with_photos_filters_and_dimensions_then_publishes_it(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $this->get('/panou/catalog/produse')->assertOk()->assertSee('Ramă nouă');
        $this->get('/panou/catalog/produse/nou')->assertOk()->assertSee('Prima variantă')->assertSee('Femei');

        $women = $this->valueId('gen', 'Femei');
        $acetate = $this->valueId('material', 'Acetat');
        $response = $this->post('/panou/catalog/produse', [
            'tip' => 'frame', 'nume' => 'Ramă Fictivă Test 5201', 'disponibilitate' => 'la_comanda', 'cu_lentile' => '1',
            'categorii' => [\App\Modules\Catalog\Models\Category::where('slug', 'rame')->value('id')], 'atribute' => [$women, $acetate],
            'latime' => 52, 'punte' => 18, 'brat' => 140, 'tip_rama' => 'full', 'descriere' => '<p>Text <script>alert(1)</script>fictiv.</p>',
            'varianta' => ['nume' => 'Negru mat', 'sku' => 'TEST-5201-NEGRU', 'pret' => '499,90', 'stoc' => 3],
            'poze' => [UploadedFile::fake()->image('fata.jpg', 1200, 800), UploadedFile::fake()->image('lateral.png', 1200, 800)],
        ]);
        $product = Product::where('name', 'Ramă Fictivă Test 5201')->firstOrFail();
        $response->assertRedirect(route('panou.catalog.product', $product->id));
        $this->assertSame(Product::STATUS_DRAFT, $product->status);
        $this->assertSame('rama-fictiva-test-5201', $product->slug);
        $this->assertStringNotContainsString('script', (string) $product->description);
        $this->assertSame(52, $product->frameSpec->lens_width_mm);
        $this->assertEqualsCanonicalizing([$women, $acetate], $product->attributeValues->pluck('id')->all());
        $variant = $product->variants()->firstOrFail();
        $this->assertSame(['TEST-5201-NEGRU', 49990, 3, true], [$variant->sku, $variant->price, $variant->stock_qty, $variant->is_active]);
        $images = ProductImage::where('product_id', $product->id)->orderBy('sort')->get();
        $this->assertCount(2, $images);
        $this->assertSame(0, $images[0]->sort);
        $this->assertTrue(\App\Core\Audit\AuditLog::where('action', 'catalog.product_created')->exists());

        // ciorna nu apare pe site; după „Publică”, da
        $this->get('/produs/'.$product->slug.'/')->assertNotFound();
        $this->post('/panou/catalog/produse/'.$product->id.'/stare', ['stare' => 'published'])->assertRedirect();
        $this->get('/produs/'.$product->slug.'/')->assertOk()->assertSee('Ramă Fictivă Test 5201')->assertSee('499,90');
    }

    public function test_variant_photo_order_and_copy(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $frame = $this->frame(30000);
        $product = $frame->product;

        $this->post('/panou/catalog/produse/'.$product->id.'/varianta-noua', ['nume' => 'Havana', 'pret' => '320', 'stoc' => ''])->assertSessionHasNoErrors();
        $this->assertSame(['Havana', 32000], [$product->variants()->reorder()->latest('id')->value('name'), (int) $product->variants()->reorder()->latest('id')->value('price')]);

        $this->post('/panou/catalog/produse/'.$product->id.'/poze', ['poze' => [UploadedFile::fake()->image('a.jpg', 800, 600), UploadedFile::fake()->image('b.jpg', 800, 600)]])->assertSessionHasNoErrors();
        [$first, $second] = ProductImage::where('product_id', $product->id)->orderBy('sort')->get()->all();
        $this->post('/panou/catalog/produse/'.$product->id.'/poze/'.$second->id, ['actiune' => 'principala'])->assertSessionHasNoErrors();
        $this->assertSame([$second->id, $first->id], ProductImage::where('product_id', $product->id)->orderBy('sort')->pluck('id')->all());
        $this->post('/panou/catalog/produse/'.$product->id.'/poze/'.$first->id, ['actiune' => 'sterge']);
        $this->assertSame(1, ProductImage::where('product_id', $product->id)->count());
        $this->assertNotNull(Media::find($first->media_id), 'fișierul rămâne în biblioteca media');

        $this->post('/panou/catalog/produse/'.$product->id.'/copie')->assertRedirect();
        $copy = Product::where('name', $product->name.' (copie)')->firstOrFail();
        $this->assertSame(Product::STATUS_DRAFT, $copy->status);
        $this->assertSame(2, $copy->variants()->count());
        $this->assertSame(1, ProductImage::where('product_id', $copy->id)->count());
        $this->assertSame(0, ProductVariant::whereIn('sku', $copy->variants()->pluck('sku'))->where('product_id', $product->id)->count(), 'coduri SKU noi');
    }

    public function test_without_prices_right_the_new_frame_waits_for_a_price_and_cannot_be_published(): void
    {
        $this->actingAsRole(Roles::EDITOR_SITE);
        $this->get('/panou/catalog/produse/nou')->assertOk()->assertSee('Prețul îl completează');
        $this->post('/panou/catalog/produse', ['tip' => 'frame', 'nume' => 'Ramă Fictivă Fără Preț', 'disponibilitate' => 'la_comanda',
            'varianta' => ['nume' => 'Gri']])->assertSessionHasNoErrors();
        $product = Product::where('name', 'Ramă Fictivă Fără Preț')->firstOrFail();
        $this->assertFalse($product->variants()->first()->is_active);
        $this->post('/panou/catalog/produse/'.$product->id.'/stare', ['stare' => 'published']);
        $this->assertSame(Product::STATUS_DRAFT, $product->fresh()->status);
    }

    public function test_viewers_cannot_create(): void
    {
        $this->actingAsRole(Roles::SPECIALIST_OPTIC);
        $this->get('/panou/catalog/produse/nou')->assertForbidden();
        $this->post('/panou/catalog/produse', ['tip' => 'frame', 'nume' => 'X'])->assertForbidden();
    }
}
