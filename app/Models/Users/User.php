<?php

namespace App\Models\Users;

use App\Models\Institution;
use App\Models\Permission;
use App\Models\Users\Profiles\DriverProfile;
use App\Models\Users\Profiles\ManagerProfile;
use App\Models\Users\Profiles\MonitorProfile;
use App\Models\Users\Profiles\PassengerProfile;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;


    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role', // 'admin', 'passenger', 'driver', 'monitor', 'manager'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Relacionamentos com roles específicas
    public function driverProfile(): HasOne
    {
        return $this->hasOne(DriverProfile::class);
    }

    public function monitorProfile(): HasOne
    {
        return $this->hasOne(MonitorProfile::class);
    }

    public function managerProfile(): HasOne
    {
        return $this->hasOne(ManagerProfile::class);
    }

    public function passengerProfile(): HasOne
    {
        return $this->hasOne(PassengerProfile::class);
    }

    /**
     * Relacionamento com instituições (relação direta)
     * Necessário para funcionar com Institution::users()
     */
    public function institutions()
    {
        return $this->belongsToMany(Institution::class, 'institution_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    // Métodos para verificar papéis
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isDriver(): bool
    {
        return $this->role === 'driver';
    }

    public function isMonitor(): bool
    {
        return $this->role === 'monitor';
    }

    public function isPassenger(): bool
    {
        return $this->role === 'passenger';
    }

    // Método para obter todas as instituições vinculadas ao usuário
    public function getInstitutions()
    {
        switch ($this->role) {
            case 'admin':
                return Institution::all();
            case 'manager':
                // Usar o método de relação em vez de acessar a propriedade diretamente
                $profile = $this->managerProfile()->first();
                if ($profile) {
                    return $profile->institutions()->get();
                }
                // Se não conseguir pela relação manager_institution, tenta pela relação direta
                return $this->institutions()->get();
            case 'driver':
                $profile = $this->driverProfile()->first();
                if ($profile) {
                    return $profile->institutions()->get();
                }
                return $this->institutions()->get();
            case 'monitor':
                $profile = $this->monitorProfile()->first();
                if ($profile) {
                    return $profile->institutions()->get();
                }
                return $this->institutions()->get();
            default:
                // Para passageiros e outros tipos, usa a relação direta institution_user
                return $this->institutions()->get();
        }
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
            ->withPivot([
                'granted_at',
                'granted_by',
                'expires_at',
                'is_active'
            ])
            ->withTimestamps()
            ->where('user_permissions.is_active', true)
            ->where(function($query) {
                $query->whereNull('user_permissions.expires_at')
                    ->orWhere('user_permissions.expires_at', '>', now());
            });
    }

}
