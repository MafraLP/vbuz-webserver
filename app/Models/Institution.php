<?php

namespace App\Models;

use App\Models\Users\User;
use App\Models\Users\Profiles\DriverProfile;
use App\Models\Users\Profiles\ManagerProfile;
use App\Models\Users\Profiles\MonitorProfile;
use App\Models\Users\Profiles\PassengerProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'city',
        'state',
        'address',
        'latitude',
        'longitude',
        'parent_id',
        'notes',
    ];

    /**
     * Relacionamento com a instituição pai
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'parent_id');
    }

    /**
     * Relacionamento com as instituições filhas
     */
    public function children(): HasMany
    {
        return $this->hasMany(Institution::class, 'parent_id');
    }

    /**
     * Relacionamento recursivo para pegar todos os filhos e netos
     */
    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }

    /**
     * Relacionamento com usuários
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'institution_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Relacionamento com perfis de motoristas
     */
    public function driverProfiles(): BelongsToMany
    {
        return $this->belongsToMany(DriverProfile::class, 'driver_institution')
            ->withPivot(['start_date', 'end_date', 'status', 'contract_type', 'schedule', 'notes'])
            ->withTimestamps();
    }

    /**
     * Relacionamento com perfis de gerentes
     */
    public function managerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(ManagerProfile::class, 'manager_institution')
            ->withPivot(['is_primary', 'permissions', 'assignment_date', 'notes'])
            ->withTimestamps();
    }

    /**
     * Relacionamento com perfis de monitores
     */
    public function monitorProfiles(): BelongsToMany
    {
        return $this->belongsToMany(MonitorProfile::class, 'monitor_institution')
            ->withPivot(['start_date', 'end_date', 'status', 'contract_type', 'schedule', 'notes'])
            ->withTimestamps();
    }

    /**
     * Relacionamento com perfis de passageiros
     */
    public function passengerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(PassengerProfile::class, 'passenger_institution')
            ->withPivot(['enrollment_code', 'enrollment_date', 'status', 'notes'])
            ->withTimestamps();
    }

    /**
     * Relacionamento com pontos
     */
    public function points(): HasMany
    {
        return $this->hasMany(Point::class);
    }

    /**
     * Relacionamento com rotas
     */
    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    /**
     * Verifica se é uma prefeitura
     */
    public function isPrefecture(): bool
    {
        return $this->type === 'prefecture';
    }

    /**
     * Obtém o caminho completo da hierarquia
     */
    public function getHierarchyPath(): string
    {
        $path = [$this->name];
        $currentParent = $this->parent;

        while ($currentParent) {
            array_unshift($path, $currentParent->name);
            $currentParent = $currentParent->parent;
        }

        return implode(' > ', $path);
    }
}
