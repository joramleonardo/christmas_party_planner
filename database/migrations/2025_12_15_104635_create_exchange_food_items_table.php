<?php

// database/migrations/xxxx_xx_xx_create_exchange_food_items_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exchange_food_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_project_id')
                ->constrained('exchange_projects')
                ->cascadeOnDelete();

            $table->string('name');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('qty')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['exchange_project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_food_items');
    }
};
