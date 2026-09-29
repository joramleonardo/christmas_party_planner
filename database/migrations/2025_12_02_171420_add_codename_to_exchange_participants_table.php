<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('exchange_participants', function (Blueprint $table) {
      $table->string('codename', 80)->nullable()->after('name');
      $table->index(['project_id', 'list_type', 'codename']);
    });
  }

  public function down(): void
  {
    Schema::table('exchange_participants', function (Blueprint $table) {
      $table->dropIndex(['project_id', 'list_type', 'codename']);
      $table->dropColumn('codename');
    });
  }
};
