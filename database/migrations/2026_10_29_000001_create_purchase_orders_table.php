<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('source'); // aliexpress, mercadolivre...
            $table->string('number');
            $table->date('ordered_at')->nullable();
            $table->string('status');
            $table->integer('total_cents')->nullable();
            $table->string('currency', 3)->default('BRL');
            $table->string('url')->nullable();
            $table->timestamps();

            $table->unique(['source', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
