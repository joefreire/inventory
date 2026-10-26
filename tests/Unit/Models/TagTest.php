<?php

namespace Tests\Unit\Models;

use App\Models\Item;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_tag(): void
    {
        $tag = Tag::create([
            'name' => 'eletronica',
            'color' => '#0d6efd',
        ]);

        $this->assertDatabaseHas('tags', [
            'name' => 'eletronica',
            'color' => '#0d6efd',
        ]);
    }

    public function test_tag_has_default_color(): void
    {
        $tag = Tag::create(['name' => 'teste']);

        $this->assertNotNull($tag->color);
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $tag->color);
    }

    public function test_get_or_create_returns_existing_tag(): void
    {
        $existing = Tag::create(['name' => 'eletronica', 'color' => '#ff0000']);

        $tag = Tag::getOrCreate('eletronica');

        $this->assertEquals($existing->id, $tag->id);
        $this->assertEquals('#ff0000', $tag->color);
    }

    public function test_get_or_create_creates_new_tag(): void
    {
        $tag = Tag::getOrCreate('nova-tag', '#00ff00');

        $this->assertDatabaseHas('tags', ['name' => 'nova-tag']);
        $this->assertEquals('#00ff00', $tag->color);
    }

    public function test_tag_can_have_items(): void
    {
        $tag = Tag::create(['name' => 'resistor']);
        $item = Item::create(['name' => 'Resistor 10k']);

        $item->tags()->attach($tag);

        $this->assertCount(1, $tag->items);
        $this->assertEquals('Resistor 10k', $tag->items->first()->name);
    }

    public function test_tag_name_is_unique(): void
    {
        Tag::create(['name' => 'eletronica']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Tag::create(['name' => 'eletronica']);
    }

    public function test_generate_color_returns_valid_hex(): void
    {
        $color = Tag::generateColor();

        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $color);
    }

    public function test_item_count_attribute(): void
    {
        $tag = Tag::create(['name' => 'eletronica']);

        Item::create(['name' => 'Item 1'])->tags()->attach($tag);
        Item::create(['name' => 'Item 2'])->tags()->attach($tag);

        $this->assertEquals(2, $tag->item_count);
    }
}
