<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_tags_index_page_loads(): void
    {
        $response = $this->get(route('tags.index'));

        $response->assertStatus(200);
        $response->assertSee('Tags');
    }

    public function test_tags_index_shows_tags(): void
    {
        Tag::create(['name' => 'eletronica', 'color' => '#0d6efd']);

        $response = $this->get(route('tags.index'));

        $response->assertStatus(200);
        $response->assertSee('eletronica');
    }

    public function test_create_page_loads(): void
    {
        $response = $this->get(route('tags.create'));

        $response->assertStatus(200);
        $response->assertSee('Nova tag');
    }

    public function test_can_store_tag(): void
    {
        $response = $this->post(route('tags.store'), [
            'name' => 'nova-tag',
            'color' => '#ff0000',
        ]);

        $response->assertRedirect(route('tags.index'));

        $this->assertDatabaseHas('tags', [
            'name' => 'nova-tag',
            'color' => '#ff0000',
        ]);
    }

    public function test_store_requires_name(): void
    {
        $response = $this->post(route('tags.store'), [
            'color' => '#ff0000',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_validates_color_format(): void
    {
        $response = $this->post(route('tags.store'), [
            'name' => 'tag',
            'color' => 'invalid',
        ]);

        $response->assertSessionHasErrors('color');
    }

    public function test_show_page_displays_tag(): void
    {
        $tag = Tag::create(['name' => 'eletronica']);

        $response = $this->get(route('tags.show', $tag));

        $response->assertStatus(200);
        $response->assertSee('eletronica');
    }

    public function test_show_page_displays_tagged_items(): void
    {
        $tag = Tag::create(['name' => 'eletronica']);
        $item = Item::create(['name' => 'Resistor']);
        $item->tags()->attach($tag);

        $response = $this->get(route('tags.show', $tag));

        $response->assertStatus(200);
        $response->assertSee('Resistor');
    }

    public function test_edit_page_loads(): void
    {
        $tag = Tag::create(['name' => 'eletronica']);

        $response = $this->get(route('tags.edit', $tag));

        $response->assertStatus(200);
        $response->assertSee('Editar tag');
    }

    public function test_can_update_tag(): void
    {
        $tag = Tag::create(['name' => 'eletronica', 'color' => '#ff0000']);

        $response = $this->put(route('tags.update', $tag), [
            'name' => 'eletronicos',
            'color' => '#00ff00',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'eletronicos',
            'color' => '#00ff00',
        ]);
    }

    public function test_update_prevents_duplicate_name(): void
    {
        Tag::create(['name' => 'existente']);
        $tag = Tag::create(['name' => 'outra']);

        $response = $this->put(route('tags.update', $tag), [
            'name' => 'existente',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_can_delete_tag(): void
    {
        $tag = Tag::create(['name' => 'para-excluir']);

        $response = $this->delete(route('tags.destroy', $tag));

        $response->assertRedirect(route('tags.index'));
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }
}
