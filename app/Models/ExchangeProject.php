<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExchangeProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'organizer_name',
        'organizer_email',
        'unique_code',
        'slug',
        'santa_secret_code',
        'gcash_name',
        'gcash_number',
        'gcash_qr_note',
    ];

    public function participants()
    {
        return $this->hasMany(ExchangeParticipant::class, 'project_id');
    }

    public function assignments()
    {
        return $this->hasMany(ExchangeAssignment::class, 'project_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

}
