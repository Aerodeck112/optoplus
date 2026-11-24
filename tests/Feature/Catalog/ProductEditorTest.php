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

        // ciorna: colegul o previzualizează (clienții nu o văd — vezi testul cu butoanele); după „Publică”, apare pentru toți
        $this->get('/produs/'.$product->slug.'/')->assertOk()->assertSee('Ciornă — clienții nu o văd');
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

    public function test_photos_linked_to_a_colour_are_shown_when_that_colour_is_chosen(): void
    {
        // 1.0.23: poza legată de o culoare apare pe site când clientul alege culoarea (și în coș)
        $this->actingAsRole(Roles::MANAGER);
        $black = $this->frame(30000);
        $product = $black->product;
        $black->update(['name' => 'Negru']);
        $this->post('/panou/catalog/produse/'.$product->id.'/varianta-noua', ['nume' => 'Havana', 'pret' => '300'])->assertSessionHasNoErrors();
        $havana = $product->variants()->reorder()->latest('id')->firstOrFail();

        $this->post('/panou/catalog/produse/'.$product->id.'/poze', ['poze' => [UploadedFile::fake()->image('comun.jpg', 800, 600)]])->assertSessionHasNoErrors();
        $this->post('/panou/catalog/produse/'.$product->id.'/poze', ['poze' => [UploadedFile::fake()->image('havana.jpg', 800, 600)], 'varianta' => $havana->id])->assertSessionHasNoErrors();
        $this->post('/panou/catalog/produse/'.$product->id.'/poze', ['poze' => [UploadedFile::fake()->image('negru.jpg', 800, 600)]])->assertSessionHasNoErrors();
        [$common, $havanaImg, $blackImg] = ProductImage::with('media')->where('product_id', $product->id)->orderBy('sort')->get()->all();
        $this->assertSame($havana->id, $havanaImg->variant_id);

        // legarea unei poze existente de o culoare, din pagina ramei
        $this->get('/panou/catalog/produse/'.$product->id)->assertOk()->assertSee('Toate culorile')->assertSee('Pentru culoarea');
        $this->post('/panou/catalog/produse/'.$product->id.'/poze/'.$blackImg->id, ['actiune' => 'culoare', 'varianta' => $black->id])->assertSessionHasNoErrors();
        $this->assertSame($black->id, $blackImg->fresh()->variant_id);
        $other = $this->frame(10000);
        $this->post('/panou/catalog/produse/'.$product->id.'/poze/'.$blackImg->id, ['actiune' => 'culoare', 'varianta' => $other->id])->assertSessionHasErrors('varianta');

        $product->refresh();
        $this->assertSame($havanaImg->id, $product->imageFor($havana->id)->id);
        $this->assertSame($blackImg->id, $product->imageFor($black->id)->id);
        $this->assertSame([$blackImg->id, $common->id, $havanaImg->id], $product->galleryFor($black->id)->pluck('id')->all());

        $html = $this->get($product->url())->assertOk()->assertSee('data-by-color', false)->getContent();
        $this->assertStringContainsString('data-variant="'.$havana->id.'"', $html);
        $this->assertStringContainsString('class="variants__photo"', $html);
        // imaginea mare inițială este a primei culori (Negru)
        preg_match('/data-gallery-main[^>]*>\s*<img[^>]+src="([^"]+)"/', $html, $m);
        $this->assertSame($blackImg->media->bestUrl(960), $m[1] ?? null);

        // în coș: poza culorii alese
        $this->post('/cos/adauga/', ['varianta' => $havana->id]);
        $this->get('/cos/')->assertOk()->assertSee($havanaImg->media->bestUrl(960), false);

        // copia păstrează legăturile pozelor cu culorile (pe variantele noi)
        $this->post('/panou/catalog/produse/'.$product->id.'/copie')->assertRedirect();
        $copy = Product::where('name', $product->name.' (copie)')->firstOrFail();
        $copyHavana = $copy->variants()->where('name', 'Havana')->firstOrFail();
        $this->assertSame(1, ProductImage::where('product_id', $copy->id)->where('variant_id', $copyHavana->id)->count());
    }

    public function test_unpublished_frame_without_orders_can_be_deleted_for_good(): void
    {
        // 1.0.24: „Șterge definitiv” — doar nepublicat și fără comenzi
        $this->actingAsRole(Roles::MANAGER);
        $frame = $this->frame(36000);
        $product = $frame->product;
        $product->update(['published_at' => now()]);
        $this->post('/panou/catalog/produse/'.$product->id.'/poze', ['poze' => [UploadedFile::fake()->image('a.jpg', 800, 600)]]);
        $mediaId = ProductImage::where('product_id', $product->id)->value('media_id');

        $this->post('/panou/catalog/produse/'.$product->id.'/sterge')->assertSessionHas('warning');
        $this->assertNotNull($product->fresh(), 'publicat: întâi se retrage');

        $this->post('/panou/catalog/produse/'.$product->id.'/stare', ['stare' => 'archived']);
        $this->get('/panou/catalog/produse/'.$product->id)->assertOk()->assertSee('Șterge definitiv');
        $this->post('/panou/catalog/produse/'.$product->id.'/sterge')->assertRedirect(route('panou.catalog.products'));
        $this->assertNull(Product::find($product->id));
        $this->assertNull(ProductVariant::find($frame->id));
        $this->assertSame(0, ProductImage::where('product_id', $product->id)->count());
        $this->assertNotNull(Media::find($mediaId), 'fișierul rămâne în biblioteca media');
        $this->assertSame('/rame/', \App\Modules\Cms\Models\Redirect::where('source_path', '/produs/'.$product->slug.'/')->value('target'));
        $this->assertTrue(\App\Core\Audit\AuditLog::where('action', 'catalog.product_deleted')->exists());

        // cu comenzi: nu se șterge
        $ordered = $this->frame(20000);
        $ordered->product->update(['status' => Product::STATUS_ARCHIVED]);
        $order = \App\Modules\Orders\Models\Order::create(['number' => 'OP-2026-66666', 'uuid' => (string) \Illuminate\Support\Str::uuid(), 'email' => 'x@demo.optoplus.invalid',
            'payment_method' => 'ramburs', 'subtotal' => 20000, 'total' => 20000, 'billing_address' => [], 'idempotency_key' => \Illuminate\Support\Str::random(40), 'pricing_version' => '1.0']);
        \Illuminate\Support\Facades\DB::table('order_items')->insert(['order_id' => $order->id, 'variant_id' => $ordered->id, 'kind' => 'product', 'name' => 'Ramă', 'quantity' => 1, 'unit_price' => 20000, 'line_total' => 20000, 'vat_rate_bp' => 0, 'vat_amount' => 0, 'snapshot' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $this->post('/panou/catalog/produse/'.$ordered->product_id.'/sterge')->assertSessionHas('warning');
        $this->assertNotNull(Product::find($ordered->product_id));

        // fără drept de publicare: interzis
        $this->actingAsRole(Roles::SPECIALIST_OPTIC);
        $this->post('/panou/catalog/produse/'.$ordered->product_id.'/sterge')->assertForbidden();
    }

    public function test_single_colour_frame_shows_its_colour_and_photos_zoom_in_high_resolution(): void
    {
        // 1.0.25: culoarea ramei cu o singură variantă (nume + mostră) și mărirea pozei la rezoluție mare
        $this->actingAsRole(Roles::MANAGER);
        $frame = $this->frame(30000);
        $product = $frame->product;
        $this->post('/panou/catalog/produse/'.$product->id.'/poze', ['poze' => [UploadedFile::fake()->image('mare.jpg', 2400, 1800), UploadedFile::fake()->image('b.jpg', 800, 600)]]);

        $this->get('/panou/catalog/produse/'.$product->id)->assertOk()->assertSee('Culoarea ramei')->assertSee('Arată mostra pe site');
        $this->post('/panou/catalog/produse/'.$product->id.'/culori', ['c' => [$frame->id => ['nume' => 'Negru mat', 'hex' => '#zz0000', 'mostra' => '1']]])->assertSessionHasErrors();
        $this->post('/panou/catalog/produse/'.$product->id.'/culori', ['c' => [$frame->id => ['nume' => 'Negru mat', 'hex' => '#1A1A1A', 'mostra' => '1']]])->assertSessionHasNoErrors();
        $this->assertSame(['Negru mat', '#1a1a1a'], [$frame->fresh()->name, $frame->fresh()->color_hex]);
        $this->assertTrue(\App\Core\Audit\AuditLog::where('action', 'catalog.colors_updated')->exists());

        $html = $this->get($product->url())->assertOk()->assertSee('class="variant-one"', false)->assertSee('Negru mat')
            ->assertSee('style="--c: #1a1a1a"', false)->assertSee('data-gallery-lightbox', false)->getContent();
        $big = ProductImage::with('media')->where('product_id', $product->id)->orderBy('sort')->first()->media;
        $this->assertSame($big->url(), $big->zoomUrl(), 'originalul (2400 px) e mai mare decât varianta de 1600 px');
        $this->assertStringContainsString('data-zoom="'.$big->url().'"', $html);

        // fără bifa „Arată mostra” → doar numele
        $this->post('/panou/catalog/produse/'.$product->id.'/culori', ['c' => [$frame->id => ['nume' => 'Negru mat', 'hex' => '#1a1a1a']]]);
        $this->assertNull($frame->fresh()->color_hex);
        $this->get($product->url())->assertOk()->assertSee('Negru mat')->assertDontSee('class="color-dot"', false);

        // varianta altui produs nu se poate modifica de aici
        $other = $this->frame(10000);
        $this->post('/panou/catalog/produse/'.$product->id.'/culori', ['c' => [$other->id => ['nume' => 'X']]])->assertNotFound();
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

    public function test_edit_buttons_in_the_list_and_on_the_site_for_staff_only(): void
    {
        $frame = $this->frame(30000);
        $product = $frame->product;
        $draft = Product::create(['type' => 'frame', 'name' => 'Ramă Fictivă Ciornă', 'slug' => 'rama-fictiva-ciorna', 'status' => Product::STATUS_DRAFT,
            'sale_unit' => 'piece', 'availability_mode' => 'la_comanda', 'configurable_with_lenses' => true]);

        // vizitator: fără bară, ciorna nu există
        $this->get('/produs/'.$product->slug.'/')->assertOk()->assertDontSee('Editează produsul');
        $this->get('/produs/'.$draft->slug.'/')->assertNotFound();

        $this->get('/rame/')->assertOk()->assertDontSee('card__edit', false);

        $this->actingAsRole(Roles::MANAGER);
        $this->get('/panou/catalog/produse')->assertOk()->assertSee('Editează')->assertSee('Previzualizare');
        $this->get('/rame/')->assertOk()->assertSee('card__edit', false)->assertSee(route('panou.catalog.product', $product->id), false);
        $this->get('/produs/'.$product->slug.'/')->assertOk()->assertSee('Editează produsul')
            ->assertSee(route('panou.catalog.product', $product->id), false)->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/produs/'.$draft->slug.'/')->assertOk()->assertSee('Ciornă — clienții nu o văd')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
