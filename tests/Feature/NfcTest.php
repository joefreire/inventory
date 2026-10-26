<?php

namespace Tests\Feature;

use App\Locations\NfcCode;
use App\Models\Item;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NfcTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_location_gets_unique_nfc_code(): void
    {
        $a = Location::create(['name' => 'Caixa A']);
        $b = Location::create(['name' => 'Caixa B']);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $a->nfc_code);
        $this->assertNotSame($a->nfc_code, $b->nfc_code);
        $this->assertStringEndsWith("/n/{$a->nfc_code}", $a->nfc_url);
    }

    public function test_tag_link_opens_location(): void
    {
        $location = Location::create(['name' => 'Caixa ESPs']);

        $this->get("/n/{$location->nfc_code}")->assertRedirect(route('locations.show', $location));
        $this->get('/n/naoexiste')->assertNotFound();
    }

    public function test_location_page_shows_nfc_link_and_store_item_panel(): void
    {
        $location = Location::create(['name' => 'Caixa ESPs']);
        Item::create(['name' => 'Rele 5V']);

        $this->get(route('locations.show', $location))
            ->assertOk()
            ->assertSee($location->nfc_url)
            ->assertSee('Guardar item aqui')
            ->assertSee('Rele 5V');
    }

    public function test_move_item_by_tag_url(): void
    {
        $location = Location::create(['name' => 'Caixa ESPs']);
        $item = Item::create(['name' => 'ESP32']);

        $this->postJson(route('items.nfc-move', $item), ['tag' => $location->nfc_url])
            ->assertOk()
            ->assertJsonPath('location.id', $location->id);

        $this->assertSame($location->id, $item->fresh()->location_id);
    }

    public function test_move_item_with_unknown_tag_fails(): void
    {
        $item = Item::create(['name' => 'ESP32']);

        $this->postJson(route('items.nfc-move', $item), ['tag' => 'https://exemplo.com/qualquer'])
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertNull($item->fresh()->location_id);
    }

    public function test_code_from_tag(): void
    {
        $this->assertSame('ab12cd34', NfcCode::fromScanned('http://192.168.0.10:8000/n/ab12cd34'));
        $this->assertSame('ab12cd34', NfcCode::fromScanned('https://inv.exemplo/n/AB12CD34/?x=1'));
        $this->assertSame('ab12cd34', NfcCode::fromScanned(' ab12cd34 '));
        $this->assertNull(NfcCode::fromScanned('https://exemplo.com/outra/coisa'));
    }

    public function test_create_item_preselects_location_from_query(): void
    {
        $location = Location::create(['name' => 'Caixa ESPs']);

        $this->get(route('items.create', ['location_id' => $location->id]))
            ->assertOk()
            ->assertSee('value="'.$location->id.'" selected', false);
    }

    public function test_nfc_url_uses_fixed_base_when_set(): void
    {
        config(['app.nfc_base_url' => 'https://inventario.exemplo/']);
        $location = Location::create(['name' => 'Caixa']);

        $this->assertSame("https://inventario.exemplo/n/{$location->nfc_code}", $location->nfc_url);
        $this->get(route('locations.show', $location))->assertDontSee('NFC_BASE_URL não definido');
    }

    public function test_read_tag_tab_resolves_scanned_text_to_box(): void
    {
        $location = Location::create(['name' => 'Caixa ESPs']);

        $this->get(route('nfc.resolve', ['tag' => $location->nfc_url]))->assertRedirect(route('nfc.open', $location->nfc_code));
        $this->get(route('nfc.resolve', ['tag' => 'https://outro.site/qualquer']))->assertRedirect(route('items.index'));
    }

    public function test_random_codes_use_the_safe_alphabet(): void
    {
        foreach (range(1, 50) as $i) {
            $this->assertMatchesRegularExpression('/^[2-9a-hjkmnp-z]{8}$/', NfcCode::random());
        }
    }
}
