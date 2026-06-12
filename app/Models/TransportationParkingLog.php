<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransportationParkingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'parking_type',
        'location',
        'parking_date',
        'billing_month',
        'start_hour',
        'end_hour',
        'total_amount',
        'notes',
    ];
}
