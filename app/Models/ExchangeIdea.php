<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExchangeIdea extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'participant_id',
        'something',
        'phrase',
    ];

    public function project()
    {
        return $this->belongsTo(ExchangeProject::class, 'project_id');
    }

    public function participant()
    {
        return $this->belongsTo(ExchangeParticipant::class, 'participant_id');
    }
}
