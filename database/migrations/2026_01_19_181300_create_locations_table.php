<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('path')->nullable(); // Materialized path: "Escritorio/Gaveta 1/Caixa A"
            $table->integer('depth')->default(0);
            $table->timestamps();

            $table->index('parent_id');
            $table->index('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
