<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
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

        Volt::test('pages.auth.login')
            ->set('form.email', 'dono@exemplo.com')
            ->set('form.password', 'senha-bem-longa')
            ->call('login')
            ->assertRedirect("/n/{$location->nfc_code}");

        $this->assertAuthenticated();
    }

    public function test_wrong_password_does_not_log_in(): void
    {
        User::factory()->create(['email' => 'dono@exemplo.com', 'password' => Hash::make('senha-bem-longa')]);

        Volt::test('pages.auth.login')
            ->set('form.email', 'dono@exemplo.com')
            ->set('form.password', 'errada')
            ->call('login')
            ->assertHasErrors('form.email');

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
}
