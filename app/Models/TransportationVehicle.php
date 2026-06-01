<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportationVehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'tank_capacity_l',
        'consumption_unit_default',
    ];

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(TransportationFuelLog::class, 'vehicle_id');
    }

    public function commuteLogs(): HasMany
    {
        return $this->hasMany(TransportationCommuteLog::class, 'vehicle_id');
    }
}
