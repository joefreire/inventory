<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_index_page_loads(): void
    {
        $response = $this->get(route('items.index'));

        $response->assertStatus(200);
        $response->assertSee('Peças');
    }

    public function test_items_index_shows_items(): void
    {
        $item = Item::create(['name' => 'Resistor 10k']);

        $response = $this->get(route('items.index'));

        $response->assertStatus(200);
        $response->assertSee('Resistor 10k');
    }

    public function test_can_search_items(): void
    {
        Item::create(['name' => 'Resistor 10k']);
        Item::create(['name' => 'Capacitor 100uF']);

        $response = $this->get(route('items.index', ['search' => 'resistor']));

        $response->assertStatus(200);
        $response->assertSee('Resistor 10k');
        $response->assertDontSee('Capacitor 100uF');
    }

    public function test_can_filter_by_location(): void
    {
        $location = Location::create(['name' => 'Escritorio']);
        Item::create(['name' => 'Item no escritorio', 'location_id' => $location->id]);
        Item::create(['name' => 'Item sem local']);

        $response = $this->get(route('items.index', ['location_id' => $location->id]));

        $response->assertStatus(200);
        $response->assertSee('Item no escritorio');
        $response->assertDontSee('Item sem local');
    }

    public function test_can_filter_by_tag(): void
    {
        $tag = Tag::create(['name' => 'eletronica']);
        $item = Item::create(['name' => 'Item com tag']);
        $item->tags()->attach($tag);

        Item::create(['name' => 'Item sem tag']);

        $response = $this->get(route('items.index', ['tag_id' => $tag->id]));

        $response->assertStatus(200);
        $response->assertSee('Item com tag');
        $response->assertDontSee('Item sem tag');
    }

    public function test_create_page_loads(): void
    {
        $response = $this->get(route('items.create'));

        $response->assertStatus(200);
        $response->assertSee('Tirar foto da peça');
    }

    public function test_can_store_item(): void
    {
        $location = Location::create(['name' => 'Escritorio']);

        $response = $this->post(route('items.store'), [
            'name' => 'Novo Resistor',
            'description' => 'Descricao do resistor',
            'category' => 'resistor',
            'quantity' => 5,
            'location_id' => $location->id,
            'tags' => 'resistor, smd, 10k',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('items', [
            'name' => 'Novo Resistor',
            'category' => 'resistor',
            'quantity' => 5,
        ]);

        $item = Item::where('name', 'Novo Resistor')->first();
        $this->assertCount(3, $item->tags);
    }

    public function test_store_requires_name(): void
    {
        $response = $this->post(route('items.store'), [
            'description' => 'Sem nome',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_show_page_displays_item(): void
    {
        $item = Item::create([
            'name' => 'Resistor 10k',
            'description' => 'Resistor de precisao',
        ]);

        $response = $this->get(route('items.show', $item));

        $response->assertStatus(200);
        $response->assertSee('Resistor 10k');
        $response->assertSee('Resistor de precisao');
    }

    public function test_edit_page_loads(): void
    {
        $item = Item::create(['name' => 'Resistor 10k']);

        $response = $this->get(route('items.edit', $item));

        $response->assertStatus(200);
        $response->assertSee('Editar peça');
        $response->assertSee('Resistor 10k');
    }

    public function test_can_update_item(): void
    {
        $item = Item::create(['name' => 'Resistor 10k']);

        $response = $this->put(route('items.update', $item), [
            'name' => 'Resistor 22k',
            'quantity' => 3,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'name' => 'Resistor 22k',
            'quantity' => 3,
        ]);
    }

    public function test_can_delete_item(): void
    {
        $item = Item::create(['name' => 'Para excluir']);

        $response = $this->delete(route('items.destroy', $item));

        $response->assertRedirect(route('items.index'));
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_can_store_item_with_photo(): void
    {
        Storage::fake('public');

        $response = $this->post(route('items.store'), [
            'name' => 'Item com foto',
            'photo' => UploadedFile::fake()->image('photo.jpg', 800, 600),
        ]);

        $response->assertRedirect();

        $item = Item::where('name', 'Item com foto')->first();
        $this->assertNotNull($item);
        $this->assertCount(1, $item->getMedia('photos'));
    }

    public function test_can_move_item(): void
    {
        $item = Item::create(['name' => 'Item movel']);
        $location = Location::create(['name' => 'Novo local']);

        $response = $this->patch(route('items.move', $item), [
            'location_id' => $location->id,
        ]);

        $response->assertJson(['success' => true]);

        $item->refresh();
        $this->assertEquals($location->id, $item->location_id);
    }

    public function test_quantity_buttons_add_and_never_go_negative(): void
    {
        $item = Item::create(['name' => 'Resistor 10k', 'quantity' => 2]);

        $this->patchJson(route('items.quantity', $item), ['delta' => 3])->assertOk()->assertJsonPath('quantity', 5);
        $this->patchJson(route('items.quantity', $item), ['delta' => -10])->assertOk()->assertJsonPath('quantity', 0);
        $this->patchJson(route('items.quantity', $item), ['delta' => 0])->assertStatus(422);
    }

    public function test_show_page_lists_specs_from_both_metadata_formats(): void
    {
        $antigo = Item::create(['name' => 'Antigo', 'ai_metadata' => ['valor' => '10k', 'vazio' => '']]);
        $novo = Item::create(['name' => 'Novo', 'ai_metadata' => [
            'specs' => ['tensao' => '5V'], 'visible_text' => 'SRD-05VDC', 'models' => ['vision' => 'x'],
        ]]);

        $this->get(route('items.show', $antigo))->assertOk()->assertSee('10k')->assertDontSee('Vazio');
        $this->get(route('items.show', $novo))->assertOk()->assertSee('5V')->assertSee('SRD-05VDC')->assertDontSee('Models');
    }

    public function test_edit_allows_zero_quantity(): void
    {
        $item = Item::create(['name' => 'Acabou', 'quantity' => 3]);

        $this->put(route('items.update', $item), ['name' => 'Acabou', 'quantity' => 0])->assertRedirect(route('items.show', $item));
        $this->assertSame(0, $item->fresh()->quantity);
    }

    public function test_save_and_next_returns_to_create_in_same_box(): void
    {
        $caixa = Location::create(['name' => 'Caixa azul']);

        $this->post(route('items.store'), ['name' => 'Rele 5V', 'location_id' => $caixa->id, 'proxima' => '1'])
            ->assertRedirect(route('items.create', ['location_id' => $caixa->id]));

        $this->get(route('items.create', ['location_id' => $caixa->id]))
            ->assertSee('value="'.$caixa->id.'" selected', false);
    }

    public function test_filter_items_without_box(): void
    {
        $caixa = Location::create(['name' => 'Caixa azul']);
        Item::create(['name' => 'Na caixa', 'location_id' => $caixa->id]);
        Item::create(['name' => 'Solta']);

        $this->get(route('items.index', ['location_id' => 'nenhuma']))
            ->assertOk()->assertSee('Solta')->assertDontSee('Na caixa');
    }

    public function test_store_saves_several_photos_in_order(): void
    {
        Storage::fake('public');

        $this->post(route('items.store'), [
            'name' => 'Rele 5V',
            'photos' => [
                UploadedFile::fake()->image('frente.jpg'),
                UploadedFile::fake()->image('verso.jpg'),
                UploadedFile::fake()->image('etiqueta.jpg'),
            ],
        ])->assertRedirect();

        $item = Item::where('name', 'Rele 5V')->firstOrFail();
        $this->assertSame(['frente.jpg', 'verso.jpg', 'etiqueta.jpg'], $item->getMedia('photos')->pluck('file_name')->all());
        $this->get(route('items.show', $item))->assertOk()->assertSee('galleryDots', false);
    }

    public function test_update_removes_chosen_photo_and_adds_new_one(): void
    {
        Storage::fake('public');
        $item = Item::create(['name' => 'Rele 5V']);
        $a = $item->addMedia(UploadedFile::fake()->image('a.jpg'))->toMediaCollection('photos');
        $item->addMedia(UploadedFile::fake()->image('b.jpg'))->toMediaCollection('photos');
        $outra = Item::create(['name' => 'Outra']);
        $alheia = $outra->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('photos');

        $this->put(route('items.update', $item), [
            'name' => 'Rele 5V',
            'remove_media' => [$a->id, $alheia->id],
            'photos' => [UploadedFile::fake()->image('c.jpg')],
        ])->assertRedirect(route('items.show', $item));

        $this->assertSame(['b.jpg', 'c.jpg'], $item->fresh()->getMedia('photos')->pluck('file_name')->all());
        $this->assertCount(1, $outra->fresh()->getMedia('photos')); // foto de outra peca nao sai
    }

    public function test_store_rejects_more_than_max_photos(): void
    {
        Storage::fake('public');
        $fotos = array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg"), range(1, 7));

        $this->post(route('items.store'), ['name' => 'Muitas', 'photos' => $fotos])->assertSessionHasErrors('photos');
    }
}
