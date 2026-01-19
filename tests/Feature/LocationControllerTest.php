<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_locations_index_page_loads(): void
    {
        $response = $this->get(route('locations.index'));

        $response->assertStatus(200);
        $response->assertSee('Locais');
    }

    public function test_locations_index_shows_locations(): void
    {
        Location::create(['name' => 'Escritorio']);

        $response = $this->get(route('locations.index'));

        $response->assertStatus(200);
        $response->assertSee('Escritorio');
    }

    public function test_create_page_loads(): void
    {
        $response = $this->get(route('locations.create'));

        $response->assertStatus(200);
        $response->assertSee('Novo Local');
    }

    public function test_can_store_location(): void
    {
        $response = $this->post(route('locations.store'), [
            'name' => 'Novo Local',
            'description' => 'Descricao do local',
        ]);

        $response->assertRedirect(route('locations.index'));

        $this->assertDatabaseHas('locations', [
            'name' => 'Novo Local',
            'description' => 'Descricao do local',
        ]);
    }

    public function test_can_store_child_location(): void
    {
        $parent = Location::create(['name' => 'Escritorio']);

        $response = $this->post(route('locations.store'), [
            'name' => 'Gaveta 1',
            'parent_id' => $parent->id,
        ]);

        $response->assertRedirect();

        $child = Location::where('name', 'Gaveta 1')->first();
        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertEquals('Escritorio/Gaveta 1', $child->path);
    }

    public function test_store_requires_name(): void
    {
        $response = $this->post(route('locations.store'), [
            'description' => 'Sem nome',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_show_page_displays_location(): void
    {
        $location = Location::create(['name' => 'Escritorio']);

        $response = $this->get(route('locations.show', $location));

        $response->assertStatus(200);
        $response->assertSee('Escritorio');
    }

    public function test_show_page_displays_items(): void
    {
        $location = Location::create(['name' => 'Escritorio']);
        Item::create(['name' => 'Resistor', 'location_id' => $location->id]);

        $response = $this->get(route('locations.show', $location));

        $response->assertStatus(200);
        $response->assertSee('Resistor');
    }

    public function test_edit_page_loads(): void
    {
        $location = Location::create(['name' => 'Escritorio']);

        $response = $this->get(route('locations.edit', $location));

        $response->assertStatus(200);
        $response->assertSee('Editar Local');
    }

    public function test_can_update_location(): void
    {
        $location = Location::create(['name' => 'Escritorio']);

        $response = $this->put(route('locations.update', $location), [
            'name' => 'Home Office',
            'description' => 'Nova descricao',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'name' => 'Home Office',
        ]);
    }

    public function test_cannot_set_location_as_own_parent(): void
    {
        $location = Location::create(['name' => 'Escritorio']);

        $response = $this->put(route('locations.update', $location), [
            'name' => 'Escritorio',
            'parent_id' => $location->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_cannot_set_child_as_parent(): void
    {
        $parent = Location::create(['name' => 'Pai']);
        $child = Location::create(['name' => 'Filho', 'parent_id' => $parent->id]);

        $response = $this->put(route('locations.update', $parent), [
            'name' => 'Pai',
            'parent_id' => $child->id,
        ]);

        $response->assertSessionHasErrors('parent_id');
    }

    public function test_can_delete_location(): void
    {
        $location = Location::create(['name' => 'Para excluir']);

        $response = $this->delete(route('locations.destroy', $location));

        $response->assertRedirect(route('locations.index'));
        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_tree_api_returns_json(): void
    {
        Location::create(['name' => 'Escritorio']);

        $response = $this->get(route('locations.tree'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => ['id', 'name', 'path', 'depth', 'item_count', 'children'],
        ]);
    }
}
