<?php

namespace App\Models\Users\Profiles;

use App\Models\Institution;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PassengerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'default_address',
        'default_latitude',
        'default_longitude',
        'emergency_contact',
        'emergency_phone',
        'special_needs',
        'notes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relação com instituições (muitos para muitos)
    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class, 'passenger_institution')
            ->withPivot(['enrollment_code', 'enrollment_date', 'status', 'notes'])
            ->withTimestamps();
    }
}
