<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('exchange_assignments', function (Blueprint $table) {
            $table->string('mode', 20)->default('multi')->after('project_id');
            $table->index(['project_id', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::table('exchange_assignments', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'mode']);
            $table->dropColumn('mode');
        });
    }
};
