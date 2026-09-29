<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exchange_projects', function (Blueprint $table) {
            $table->string('gcash_name', 255)->nullable()->after('santa_secret_code');
            $table->string('gcash_number', 50)->nullable()->after('gcash_name');
            $table->text('gcash_qr_note')->nullable()->after('gcash_number');
        });

        Schema::table('exchange_food_payers', function (Blueprint $table) {
            $table->string('payment_status', 30)->default('unpaid')->after('pay_amount');
            $table->string('payment_reference', 100)->nullable()->after('payment_status');
            $table->text('payment_notes')->nullable()->after('payment_reference');
        });

        Schema::table('exchange_assignments', function (Blueprint $table) {
            $table->boolean('is_bought')->default(false)->after('gift_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exchange_projects', function (Blueprint $table) {
            $table->dropColumn(['gcash_name', 'gcash_number', 'gcash_qr_note']);
        });

        Schema::table('exchange_food_payers', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_reference', 'payment_notes']);
        });

        Schema::table('exchange_assignments', function (Blueprint $table) {
            $table->dropColumn(['is_bought']);
        });
    }
};
