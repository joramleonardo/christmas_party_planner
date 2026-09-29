<?php

// database/migrations/xxxx_xx_xx_create_exchange_food_payers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('exchange_food_payers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exchange_project_id')
                ->constrained('exchange_projects')
                ->cascadeOnDelete();

            $table->foreignId('exchange_participant_id')
                ->constrained('exchange_participants')
                ->cascadeOnDelete();

            $table->boolean('is_boss')->default(false);

            // This is the TOTAL amount this participant will pay (not extra)
            $table->decimal('pay_amount', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(['exchange_project_id', 'exchange_participant_id']);
            $table->index(['exchange_project_id', 'is_boss']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_food_payers');
    }
};
