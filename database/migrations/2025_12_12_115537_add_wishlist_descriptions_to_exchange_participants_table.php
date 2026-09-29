<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_participants', function (Blueprint $table) {
            $table->text('wishlist_1_description')->nullable()->after('wishlist_1');
            $table->text('wishlist_2_description')->nullable()->after('wishlist_2');
            $table->text('wishlist_3_description')->nullable()->after('wishlist_3');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_participants', function (Blueprint $table) {
            $table->dropColumn([
                'wishlist_1_description',
                'wishlist_2_description',
                'wishlist_3_description',
            ]);
        });
    }
};
