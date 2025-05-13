<?php
namespace App\Models\Users\Profiles;

use App\Models\Institution;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ManagerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'position',
        'department',
        'hire_date',
        'access_level',
        'notes',
    ];

    protected $casts = [
        'hire_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relação com instituições (muitos para muitos)
    public function institutions(): BelongsToMany
    {
        return $this->belongsToMany(Institution::class, 'manager_institution')
            ->withPivot(['is_primary', 'permissions', 'assignment_date', 'notes'])
            ->withTimestamps();
    }
}
