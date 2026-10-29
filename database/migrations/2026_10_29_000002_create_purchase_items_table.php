<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('line_key', 40); // sha1(title_truncated|variant)
            $table->string('title_truncated');
            $table->string('variant')->nullable();
            $table->integer('quantity')->default(1);
            $table->string('image_url')->nullable();

            // Preenchido pela IA
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->json('specs')->nullable();
            $table->integer('pack_quantity')->nullable();
            $table->boolean('is_part')->nullable(); // nulo = ainda nao enriquecido
            $table->timestamp('enriched_at')->nullable();
            $table->string('enrichment_error')->nullable();

            // Ligacao com o estoque
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('proposed_item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->float('proposal_probability')->nullable();
            $table->timestamp('proposal_rejected_at')->nullable();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'line_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
