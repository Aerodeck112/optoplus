<?php

namespace Tests\Feature\Reviews;

use App\Core\Audit\AuditLog;
use App\Modules\Identity\Roles;
use App\Modules\Orders\Models\Order;
use App\Modules\Reviews\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsCatalog;
use Tests\TestCase;

/** 1.0.27: recenzii fără cont, pe produs și despre magazin; publicate doar după moderare. Date fictive. */
class ReviewsTest extends TestCase
{
    use BuildsCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CatalogBaseSeeder::class);
    }

    public function test_visitor_reviews_a_product_without_account_and_it_appears_after_moderation(): void
    {
        $frame = $this->frame(30000);
        $product = $frame->product;
        $this->get($product->url())->assertOk()->assertSee('id="recenzii"', false)->assertSee('Scrieți o recenzie despre această ramă');

        // cumpărare verificată: emailul are o comandă cu această ramă
        $order = Order::create(['number' => 'OP-2026-55555', 'uuid' => (string) Str::uuid(), 'email' => 'ana.fictiva@demo.optoplus.invalid',
            'payment_method' => 'ramburs', 'subtotal' => 30000, 'total' => 30000, 'billing_address' => [], 'idempotency_key' => Str::random(40), 'pricing_version' => '1.0']);
        DB::table('order_items')->insert(['order_id' => $order->id, 'variant_id' => $frame->id, 'kind' => 'product', 'name' => 'Ramă', 'quantity' => 1,
            'unit_price' => 30000, 'line_total' => 30000, 'vat_rate_bp' => 0, 'vat_amount' => 0, 'snapshot' => '{}', 'created_at' => now(), 'updated_at' => now()]);

        $this->post('/recenzii/', ['produs' => $product->id, 'nota' => 5, 'mesaj' => 'Rama arată foarte bine și e ușoară.', 'nume' => 'Ana Fictivescu',
            'email' => 'Ana.Fictiva@demo.optoplus.invalid', 'acord' => '1'])->assertRedirect()->assertSessionHas('review_sent');
        $review = Review::firstOrFail();
        $this->assertSame('noua', $review->status);
        $this->assertTrue($review->verified);
        $this->assertSame($order->id, $review->order_id);
        $this->get($product->url())->assertOk()->assertDontSee('Rama arată foarte bine', false);

        $this->actingAsRole(Roles::MANAGER);
        $this->get('/panou/recenzii')->assertOk()->assertSee('Rama arată foarte bine')->assertSee('ana.fictiva@demo.optoplus.invalid');
        $this->post('/panou/recenzii/'.$review->id, ['actiune' => 'publica'])->assertSessionHasNoErrors();
        $this->post('/panou/recenzii/'.$review->id, ['actiune' => 'raspuns', 'raspuns' => 'Mulțumim frumos!']);
        $this->assertTrue(AuditLog::where('action', 'reviews.publica')->exists());

        auth()->logout();
        $html = $this->get($product->url())->assertOk()->assertSee('Rama arată foarte bine')->assertSee('Ana F.')
            ->assertDontSee('Fictivescu')->assertDontSee('ana.fictiva@demo.optoplus.invalid')
            ->assertSee('Cumpărare verificată')->assertSee('Mulțumim frumos!')->getContent();
        $this->assertStringContainsString('"aggregateRating"', $html);
    }

    public function test_shop_feedback_on_home_validation_honeypot_and_permissions(): void
    {
        $this->seed([\Database\Seeders\BusinessSettingsSeeder::class, \Database\Seeders\SiteContentSeeder::class]);
        $this->get('/')->assertOk()->assertSee('Părerile clienților')->assertSee('Lăsați-ne o părere');

        $this->post('/recenzii/', ['nota' => 4, 'mesaj' => 'scurt', 'nume' => '', 'acord' => ''])->assertSessionHasErrors(['mesaj', 'nume', 'acord']);
        $this->post('/recenzii/', ['nota' => 5, 'mesaj' => 'Cumpărați de aici https://spam.invalid și https://spam2.invalid', 'nume' => 'Robot', 'acord' => '1'])->assertSessionHasErrors('mesaj');
        $this->post('/recenzii/', ['nota' => 5, 'mesaj' => 'Text completat de un robot fictiv.', 'nume' => 'Robot', 'acord' => '1', 'website' => 'http://spam.invalid']);
        $this->assertSame(0, Review::count(), 'câmpul-capcană completat → nu se salvează');

        $this->post('/recenzii/', ['nota' => 4, 'mesaj' => 'Comanda a ajuns repede, recomand.', 'nume' => 'Ion Fictiv', 'acord' => '1'])->assertSessionHasNoErrors();
        $review = Review::firstOrFail();
        $this->assertNull($review->product_id);
        $this->assertFalse($review->verified);

        $this->actingAsRole(Roles::SPECIALIST_OPTIC);
        $this->get('/panou/recenzii')->assertForbidden();
        $this->post('/panou/recenzii/'.$review->id, ['actiune' => 'publica'])->assertForbidden();

        $this->actingAsRole(Roles::MANAGER);
        $this->post('/panou/recenzii/'.$review->id, ['actiune' => 'publica']);
        auth()->logout();
        $this->get('/')->assertOk()->assertSee('Comanda a ajuns repede')->assertSee('Ion F.');
    }
}
