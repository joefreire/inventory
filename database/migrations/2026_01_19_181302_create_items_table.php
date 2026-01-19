<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('ai_description')->nullable(); // Gerado pela IA
            $table->string('category')->nullable();
            $table->integer('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->json('ai_metadata')->nullable(); // Specs extras da IA
            $table->timestamps();

            $table->index('location_id');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
