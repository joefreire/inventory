<?php

namespace Tests\Unit\Models;

use App\Models\Item;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_location(): void
    {
        $location = Location::create([
            'name' => 'Escritorio',
            'description' => 'Sala de trabalho',
        ]);

        $this->assertDatabaseHas('locations', [
            'name' => 'Escritorio',
            'description' => 'Sala de trabalho',
        ]);
    }

    public function test_location_has_path_attribute(): void
    {
        $location = Location::create(['name' => 'Escritorio']);

        $this->assertEquals('Escritorio', $location->path);
        $this->assertEquals(0, $location->depth);
    }

    public function test_can_create_child_location(): void
    {
        $parent = Location::create(['name' => 'Escritorio']);
        $child = Location::create([
            'name' => 'Gaveta 1',
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertEquals('Escritorio/Gaveta 1', $child->path);
        $this->assertEquals(1, $child->depth);
    }

    public function test_location_can_have_items(): void
    {
        $location = Location::create(['name' => 'Escritorio']);
        $item = Item::create([
            'name' => 'Resistor 10k',
            'location_id' => $location->id,
        ]);

        $this->assertCount(1, $location->items);
        $this->assertEquals('Resistor 10k', $location->items->first()->name);
    }

    public function test_ancestors_returns_correct_order(): void
    {
        $level1 = Location::create(['name' => 'Casa']);
        $level2 = Location::create(['name' => 'Escritorio', 'parent_id' => $level1->id]);
        $level3 = Location::create(['name' => 'Gaveta', 'parent_id' => $level2->id]);

        $ancestors = $level3->ancestors();

        $this->assertCount(2, $ancestors);
        $this->assertEquals('Casa', $ancestors[0]->name);
        $this->assertEquals('Escritorio', $ancestors[1]->name);
    }

    public function test_total_item_count_includes_children(): void
    {
        $parent = Location::create(['name' => 'Escritorio']);
        $child = Location::create(['name' => 'Gaveta', 'parent_id' => $parent->id]);

        Item::create(['name' => 'Item 1', 'location_id' => $parent->id]);
        Item::create(['name' => 'Item 2', 'location_id' => $child->id]);
        Item::create(['name' => 'Item 3', 'location_id' => $child->id]);

        $this->assertEquals(3, $parent->total_item_count);
        $this->assertEquals(2, $child->total_item_count);
    }

    public function test_deleting_parent_deletes_children(): void
    {
        $parent = Location::create(['name' => 'Escritorio']);
        $child = Location::create(['name' => 'Gaveta', 'parent_id' => $parent->id]);

        $parent->delete();

        $this->assertDatabaseMissing('locations', ['id' => $parent->id]);
        $this->assertDatabaseMissing('locations', ['id' => $child->id]);
    }
}
