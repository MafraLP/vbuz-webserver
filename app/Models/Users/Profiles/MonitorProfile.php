<?php
namespace App\Models\Users\Profiles;

use App\Models\Institution;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MonitorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'position',
        'hire_date',
        'status',
        'employment_type',
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
        return $this->belongsToMany(Institution::class, 'monitor_institution')
            ->withPivot(['start_date', 'end_date', 'status', 'contract_type', 'schedule', 'notes'])
            ->withTimestamps();
    }
}
