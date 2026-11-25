<?php

namespace Tests\Feature\Merchant;

use App\Core\Settings\Settings;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Identity\Roles;
use App\Modules\Merchant\Services\MerchantFeed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\BuildsCatalog;
use Tests\TestCase;

/** 1.0.26: fluxul de produse pentru Google Merchant Center și tabul din Setări. Date fictive. */
class MerchantFeedTest extends TestCase
{
    use BuildsCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CatalogBaseSeeder::class);
        config(['optoplus.media_path' => storage_path('framework/testing/media'), 'optoplus.site.canonical_host' => 'https://magazin.demo.optoplus.invalid']);
        $s = app(Settings::class);
        $s->set('shipping.courier_enabled', true);
        $s->set('shipping.courier_price', 2600);
        $s->set('shipping.free_from', null);
        Cache::flush();
    }

    private function photo(\App\Modules\Catalog\Models\Product $product, ?int $variantId = null, int $width = 2000): void
    {
        // dimensiuni diferite → fișiere diferite (biblioteca media nu dublează fișierele identice)
        $media = app(\App\Modules\Cms\Services\MediaLibrary::class)->upload(UploadedFile::fake()->image('r'.uniqid().'.jpg', $width, 1500), $product->name, 'catalog');
        ProductImage::create(['product_id' => $product->id, 'variant_id' => $variantId, 'media_id' => $media->id, 'status' => 'ready', 'sort' => 0]);
    }

    public function test_feed_is_off_until_enabled_and_lists_frames_with_price_shipping_and_colours(): void
    {
        $this->get('/feed/google-merchant-ro.xml')->assertNotFound();

        $brand = Brand::create(['name' => 'Marcă Fictivă', 'slug' => 'marca-fictiva-feed']);
        $frame = $this->frame(30000);
        $frame->update(['sku' => 'FICTIV-1', 'sku_generated' => false, 'name' => 'Negru']);
        $frame->product->update(['brand_id' => $brand->id]);
        $havana = $frame->product->variants()->create(['sku' => 'FICTIV-2', 'name' => 'Havana', 'price' => 32000, 'is_active' => true]);
        $this->photo($frame->product);
        $this->photo($frame->product, $havana->id, 2100);
        $noPhoto = $this->frame(15000);

        app(Settings::class)->set('merchant.enabled', true);
        $res = $this->get('/feed/google-merchant-ro.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $xml = simplexml_load_string($res->getContent());
        $this->assertNotFalse($xml, 'XML valid');
        $items = $xml->channel->item;
        $g = fn ($item, $k) => (string) $item->children('http://base.google.com/ns/1.0')->{$k};
        $this->assertCount(2, $items, 'două culori; rama fără poză nu se trimite');
        $first = $items[0];
        $this->assertSame('FICTIV-1', $g($first, 'id'));
        $this->assertSame('300.00 RON', $g($first, 'price'));
        $this->assertSame('in_stock', $g($first, 'availability'));
        $this->assertSame('Marcă Fictivă', $g($first, 'brand'));
        $this->assertSame('FICTIV-1', $g($first, 'mpn'));
        $this->assertSame('Negru', $g($first, 'color'));
        $this->assertSame('OP-P'.$frame->product_id, $g($first, 'item_group_id'));
        $this->assertSame('https://magazin.demo.optoplus.invalid/produs/'.$frame->product->slug.'/', $g($first, 'link'));
        $this->assertStringStartsWith('https://magazin.demo.optoplus.invalid/media/', $g($first, 'image_link'));
        $ship = $first->children('http://base.google.com/ns/1.0')->shipping;
        $this->assertSame('RO', (string) $ship->children('http://base.google.com/ns/1.0')->country);
        $this->assertSame('26.00 RON', (string) $ship->children('http://base.google.com/ns/1.0')->price);
        $this->assertSame('320.00 RON', $g($items[1], 'price'));
        $this->assertNotSame($g($first, 'image_link'), $g($items[1], 'image_link'), 'fiecare culoare cu poza ei');

        $report = app(MerchantFeed::class)->build('ro');
        $this->assertTrue(collect($report['excluded'])->contains(fn ($x) => $x['product_id'] === $noPhoto->product_id));

        // stocul epuizat → out_of_stock; „de confirmat” exclus la cerere
        $havana->update(['stock_qty' => 0]);
        $this->assertSame('out_of_stock', collect(app(MerchantFeed::class)->build('ro')['items'])->firstWhere('id', 'FICTIV-2')['availability']);
        app(Settings::class)->set('merchant.unconfirmed_stock', 'exclude');
        $this->assertSame(['FICTIV-2'], collect(app(MerchantFeed::class)->build('ro')['items'])->pluck('id')->all());

        // fluxul pentru Ungaria: oprit dacă livrarea în Ungaria e oprită
        app(Settings::class)->set('shipping.hu_enabled', false);
        $this->get('/feed/google-merchant-hu.xml')->assertNotFound();
    }

    public function test_settings_tab_saves_the_verification_tag_and_shows_the_feed_links(): void
    {
        $this->actingAsRole(Roles::MANAGER);
        $this->withRecentReauth();
        $this->get('/panou/setari/google')->assertOk()->assertSee('Google Merchant')->assertSee('merchants.google.com', false);

        $this->post('/panou/setari/google', ['merchant__enabled' => '1', 'merchant__verification' => '<meta name="google-site-verification" content="AbCdEf_1234567890-xyz" />', 'merchant__unconfirmed_stock' => 'in_stock'])
            ->assertSessionHasNoErrors();
        $this->assertSame('AbCdEf_1234567890-xyz', app(Settings::class)->get('merchant.verification'));
        $this->get('/panou/setari/google')->assertOk()->assertSee('/feed/google-merchant-ro.xml', false);
        $this->get('/rame/')->assertOk()->assertSee('<meta name="google-site-verification" content="AbCdEf_1234567890-xyz">', false);

        $this->post('/panou/setari/google', ['merchant__verification' => '<script>alert(1)</script>'])->assertSessionHasErrors('merchant.verification');
        $this->assertSame('AbCdEf_1234567890-xyz', app(Settings::class)->get('merchant.verification'));
    }
}
