<?php

namespace Tests\Unit\Models;

use App\Models\Item;
use App\Models\Location;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_item(): void
    {
        $item = Item::create([
            'name' => 'Resistor 10k',
            'description' => 'Resistor de 10k ohms',
            'category' => 'resistor',
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('items', [
            'name' => 'Resistor 10k',
            'category' => 'resistor',
            'quantity' => 10,
        ]);
    }

    public function test_item_belongs_to_location(): void
    {
        $location = Location::create(['name' => 'Escritorio']);
        $item = Item::create([
            'name' => 'Resistor 10k',
            'location_id' => $location->id,
        ]);

        $this->assertEquals('Escritorio', $item->location->name);
    }

    public function test_item_can_have_tags(): void
    {
        $item = Item::create(['name' => 'Resistor 10k']);
        $tag1 = Tag::create(['name' => 'resistor']);
        $tag2 = Tag::create(['name' => 'smd']);

        $item->tags()->attach([$tag1->id, $tag2->id]);

        $this->assertCount(2, $item->tags);
    }

    public function test_sync_tags_creates_new_tags(): void
    {
        $item = Item::create(['name' => 'Resistor 10k']);

        $item->syncTags(['resistor', 'smd', '10k']);

        $this->assertCount(3, $item->tags);
        $this->assertDatabaseHas('tags', ['name' => 'resistor']);
        $this->assertDatabaseHas('tags', ['name' => 'smd']);
        $this->assertDatabaseHas('tags', ['name' => '10k']);
    }

    public function test_sync_tags_uses_existing_tags(): void
    {
        $existingTag = Tag::create(['name' => 'eletronica']);
        $item = Item::create(['name' => 'Resistor 10k']);

        $item->syncTags(['eletronica', 'nova']);

        $this->assertEquals(2, Tag::count()); // 1 existente + 1 nova
    }

    public function test_search_scope_finds_by_name(): void
    {
        Item::create(['name' => 'Resistor 10k']);
        Item::create(['name' => 'Capacitor 100uF']);

        $results = Item::search('resistor')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Resistor 10k', $results->first()->name);
    }

    public function test_search_scope_finds_by_description(): void
    {
        Item::create(['name' => 'Item A', 'description' => 'Componente eletronico']);
        Item::create(['name' => 'Item B', 'description' => 'Parafuso']);

        $results = Item::search('eletronico')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Item A', $results->first()->name);
    }

    public function test_in_location_scope(): void
    {
        $location = Location::create(['name' => 'Escritorio']);

        Item::create(['name' => 'Item 1', 'location_id' => $location->id]);
        Item::create(['name' => 'Item 2']);

        $results = Item::inLocation($location->id)->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Item 1', $results->first()->name);
    }

    public function test_with_tag_scope(): void
    {
        $tag = Tag::create(['name' => 'eletronica']);

        $item1 = Item::create(['name' => 'Item 1']);
        $item1->tags()->attach($tag);

        Item::create(['name' => 'Item 2']);

        $results = Item::withTag($tag->id)->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Item 1', $results->first()->name);
    }

    public function test_in_category_scope(): void
    {
        Item::create(['name' => 'Resistor', 'category' => 'resistor']);
        Item::create(['name' => 'Capacitor', 'category' => 'capacitor']);

        $results = Item::inCategory('resistor')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Resistor', $results->first()->name);
    }

    public function test_ai_metadata_is_cast_to_array(): void
    {
        $item = Item::create([
            'name' => 'IC',
            'ai_metadata' => ['modelo' => 'ATmega328', 'tensao' => '5V'],
        ]);

        $this->assertIsArray($item->ai_metadata);
        $this->assertEquals('ATmega328', $item->ai_metadata['modelo']);
    }
}
