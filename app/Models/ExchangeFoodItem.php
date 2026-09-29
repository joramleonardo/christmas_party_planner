<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeFoodItem extends Model
{
    protected $fillable = [
        'exchange_project_id',
        'name',
        'unit_price',
        'qty',
        'notes',
    ];

    public function project()
    {
        return $this->belongsTo(ExchangeProject::class, 'exchange_project_id');
    }
}
