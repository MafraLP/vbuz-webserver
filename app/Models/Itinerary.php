// app/Models/Itinerary.php
<?php

namespace App\Models;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Itinerary extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'institution_id',
        'driver_id',
        'monitor_id',
        'vehicle_id',
        'status',
        'departure_time',
        'return_time',
        'days_of_week',
        'route_data',
        'is_recurring',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'route_data' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_recurring' => 'boolean',
    ];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function monitor()
    {
        return $this->belongsTo(User::class, 'monitor_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function passengers()
    {
        return $this->belongsToMany(User::class, 'itinerary_passenger', 'itinerary_id', 'passenger_id')
            ->withPivot('pickup_address', 'dropoff_address', 'pickup_lat', 'pickup_lng',
                'dropoff_lat', 'dropoff_lng', 'sequence', 'status')
            ->whereHas('passengerProfile');
    }

    public function stops()
    {
        return $this->hasMany(ItineraryStop::class)->orderBy('sequence');
    }
}
