<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportationCommuteLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'commute_type',
        'origin',
        'destination',
        'distance_km',
        'consumption_value',
        'consumption_unit',
        'driven_at',
        'notes',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TransportationVehicle::class, 'vehicle_id');
    }
}
