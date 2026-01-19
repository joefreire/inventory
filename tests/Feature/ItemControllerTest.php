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
        $response->assertSee('Itens');
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
        $response->assertSee('Adicionar Novo Item');
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
        $response->assertSee('Editar Item');
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
}
