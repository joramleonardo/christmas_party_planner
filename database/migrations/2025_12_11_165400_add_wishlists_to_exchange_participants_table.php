<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_participants', function (Blueprint $table) {
            $table->string('wishlist_1')->nullable()->after('codename');
            $table->string('wishlist_2')->nullable()->after('wishlist_1');
            $table->string('wishlist_3')->nullable()->after('wishlist_2');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_participants', function (Blueprint $table) {
            $table->dropColumn(['wishlist_1', 'wishlist_2', 'wishlist_3']);
        });
    }
};
