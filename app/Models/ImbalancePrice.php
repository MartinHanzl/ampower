<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImbalancePrice extends Model
{
    protected $table = 'imbalance_prices';

    protected $primaryKey = null;
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'time',
        'eic',
        'price_up',
        'price_down',
        'currency',
        'unit',
        'source',
    ];

    protected $casts = [
        'time' => 'datetime',
        'price_up' => 'decimal:3',
        'price_down' => 'decimal:3',
    ];
}
