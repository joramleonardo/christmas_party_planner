<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'list_type',
        'name',
        'email',
        'codename',
        'unique_code',
        'budget_vote',
        'wishlist_1',
        'wishlist_2',
        'wishlist_3',
        'wishlist_1_description',
        'wishlist_2_description',
        'wishlist_3_description',
    ];

    public function givenAssignments()
    {
        return $this->hasMany(ExchangeAssignment::class, 'giver_id');
    }

    public function receivedAssignments()
    {
        return $this->hasMany(ExchangeAssignment::class, 'receiver_id');
    }

    public function project()
    {
        return $this->belongsTo(ExchangeProject::class, 'project_id');
    }
}
