<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Artisan::call('optoplus:sync-permissions');
    }

    /**
     * Utilizator autentificat în panou, cu MFA trecut, având rolurile date.
     */
    protected function actingAsRole(string|array $roles, array $attributes = []): User
    {
        $user = User::factory()->withMfa()->create($attributes);
        $user->syncRoles((array) $roles);
        $this->actingAs($user)->withSession(['auth.started_at' => time(), 'mfa.passed' => true]);

        return $user;
    }

    /** Siteul public folosește URL-uri cu „/” final (TrailingSlash); clientul de test nu trebuie să-l elimine. */
    protected function prepareUrlForRequest($uri)
    {
        $url = parent::prepareUrlForRequest($uri);
        $path = parse_url((string) $uri, PHP_URL_PATH) ?? '';

        return ($path !== '/' && str_ends_with($path, '/') && ! str_contains($url, '?')) ? $url.'/' : (str_contains($url, '?') && str_ends_with($path, '/') ? preg_replace('/\?/', '/?', $url, 1) : $url);
    }

    /** Marchează reconfirmarea identității ca recentă, pentru rutele cu middleware „reauth”. */
    protected function withRecentReauth(): static
    {
        return $this->withSession(['auth.started_at' => time(), 'mfa.passed' => true, 'auth.reauth_at' => time()]);
    }
}
