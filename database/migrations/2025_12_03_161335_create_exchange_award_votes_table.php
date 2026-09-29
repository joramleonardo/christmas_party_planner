<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('exchange_award_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('exchange_projects')
                ->onDelete('cascade');

            $table->string('award_key', 100);

            $table->foreignId('voter_participant_id')
                ->constrained('exchange_participants')
                ->onDelete('cascade');

            $table->foreignId('nominee_participant_id')
                ->constrained('exchange_participants')
                ->onDelete('cascade');

            $table->timestamps();

            // One vote per award per voter
            $table->unique(
                ['project_id', 'award_key', 'voter_participant_id'],
                'award_unique_vote'
            );
        });
    }

    public function down()
    {
        Schema::dropIfExists('exchange_award_votes');
    }
};
