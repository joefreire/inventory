<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $logado = false;

    public function test_every_page_requires_login(): void
    {
        $location = Location::create(['name' => 'Caixa']);

        foreach (['/', '/items', '/items/create', '/items/ask', '/locations', '/tags', "/n/{$location->nfc_code}"] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        $this->postJson(route('items.analyze'), ['name' => 'x'])->assertUnauthorized();
    }

    public function test_tag_link_returns_to_box_after_login(): void
    {
        $location = Location::create(['name' => 'Caixa ESPs']);
        User::factory()->create(['email' => 'dono@exemplo.com', 'password' => Hash::make('senha-bem-longa')]);

        $this->get("/n/{$location->nfc_code}")->assertRedirect(route('login'));

        $this->post(route('login'), ['email' => 'dono@exemplo.com', 'password' => 'senha-bem-longa'])
            ->assertRedirect("/n/{$location->nfc_code}");

        $this->assertAuthenticated();
    }

    public function test_wrong_password_does_not_log_in(): void
    {
        User::factory()->create(['email' => 'dono@exemplo.com', 'password' => Hash::make('senha-bem-longa')]);

        $this->post(route('login'), ['email' => 'dono@exemplo.com', 'password' => 'errada'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_there_is_no_public_registration(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_command_creates_user_and_rejects_short_password(): void
    {
        $this->artisan('inventario:usuario', ['email' => 'dono@exemplo.com'])
            ->expectsQuestion('Senha (min. 12 caracteres)', 'curta')
            ->assertExitCode(1);

        $this->artisan('inventario:usuario', ['email' => 'dono@exemplo.com'])
            ->expectsQuestion('Senha (min. 12 caracteres)', 'senha-bem-longa')
            ->assertExitCode(0);

        $this->assertTrue(Hash::check('senha-bem-longa', User::where('email', 'dono@exemplo.com')->value('password')));
    }

    public function test_login_locks_after_five_wrong_attempts(): void
    {
        User::factory()->create(['email' => 'dono@exemplo.com', 'password' => Hash::make('senha-bem-longa')]);

        foreach (range(1, 5) as $i) {
            $this->post(route('login'), ['email' => 'dono@exemplo.com', 'password' => 'errada']);
        }

        $this->post(route('login'), ['email' => 'dono@exemplo.com', 'password' => 'senha-bem-longa'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_responses_carry_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("script-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('unsafe-eval', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_photos_are_not_public(): void
    {
        $this->get('/media/1/thumb')->assertRedirect(route('login'));
    }
}
