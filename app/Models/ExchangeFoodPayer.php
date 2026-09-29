<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeFoodPayer extends Model
{
    protected $fillable = [
        'exchange_project_id',
        'exchange_participant_id',
        'is_boss',
        'pay_amount',
        'payment_status',
        'payment_reference',
        'payment_notes',
    ];

    public function project()
    {
        return $this->belongsTo(ExchangeProject::class, 'exchange_project_id');
    }

    public function participant()
    {
        return $this->belongsTo(ExchangeParticipant::class, 'exchange_participant_id');
    }
}
