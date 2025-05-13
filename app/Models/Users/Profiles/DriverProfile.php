<?php
// DriverProfile.php
namespace App\Models\Users\Profiles;

use App\Models\Institution;
use App\Models\Itinerary;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DriverProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'license_number',
        'license_type',
        'license_expiry',
        'status',
        'hire_date',
        'employment_type',
        'notes',
    ];

    protected $casts = [
        'license_expiry' => 'date',
        'hire_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relação com instituições (muitos para muitos)
    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class, 'driver_institution')
            ->withPivot(['start_date', 'end_date', 'status', 'contract_type', 'schedule', 'notes'])
            ->withTimestamps();
    }

    // Itinerários atribuídos a este motorista
    public function itineraries()
    {
        return $this->hasMany(Itinerary::class, 'driver_id', 'user_id');
    }
}
