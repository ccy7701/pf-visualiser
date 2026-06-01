<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportationFuelLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'odometer_km',
        'fuel_litres',
        'fuel_price_mode',
        'price_per_litre',
        'total_amount',
        'fuelled_at',
        'location',
        'notes',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TransportationVehicle::class, 'vehicle_id');
    }
}
