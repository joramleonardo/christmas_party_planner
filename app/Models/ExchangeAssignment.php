<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'mode',
        'giver_id',
        'receiver_id',
        'gift_number',
        'is_bought',
    ];

    protected $casts = [
        'is_bought' => 'boolean',
    ];

    public function giver()
    {
        return $this->belongsTo(ExchangeParticipant::class, 'giver_id');
    }

    public function receiver()
    {
        return $this->belongsTo(ExchangeParticipant::class, 'receiver_id');
    }

    public function project()
    {
        return $this->belongsTo(ExchangeProject::class, 'project_id');
    }
}
