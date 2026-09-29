<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeAwardVote extends Model
{
    protected $fillable = [
        'project_id',
        'award_key',
        'voter_participant_id',
        'nominee_participant_id',
    ];

    public function project()
    {
        return $this->belongsTo(ExchangeProject::class, 'project_id');
    }

    public function voter()
    {
        return $this->belongsTo(ExchangeParticipant::class, 'voter_participant_id');
    }

    public function nominee()
    {
        return $this->belongsTo(ExchangeParticipant::class, 'nominee_participant_id');
    }
}
