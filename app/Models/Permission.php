<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Institution;
use App\Models\Users\User;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'color',
        'icon',
        'is_active',
        'institution_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relacionamento com a instituição
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Relacionamento com as rotas que requerem esta permissão
     */
    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'route_permissions')
            ->withTimestamps();
    }

    /**
     * Relacionamento com os usuários que possuem esta permissão
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions')
            ->withPivot(['granted_at', 'granted_by', 'expires_at', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Usuários ativos com esta permissão
     */
    public function activeUsers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('is_active', true)
            ->wherePivot(function($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope para permissões ativas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para permissões de uma instituição específica
     */
    public function scopeForInstitution($query, $institutionId)
    {
        return $query->where('institution_id', $institutionId);
    }

    /**
     * Verifica se a permissão está ativa
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Verifica se um usuário possui esta permissão ativa
     */
    public function userHasPermission(User $user): bool
    {
        return $this->activeUsers()
            ->where('users.id', $user->id)
            ->exists();
    }

    /**
     * Concede esta permissão a um usuário
     */
    public function grantToUser(User $user, ?User $grantedBy = null, ?\DateTime $expiresAt = null): void
    {
        $this->users()->syncWithoutDetaching([
            $user->id => [
                'granted_at' => now(),
                'granted_by' => $grantedBy?->id,
                'expires_at' => $expiresAt,
                'is_active' => true,
            ]
        ]);
    }

    /**
     * Revoga esta permissão de um usuário
     */
    public function revokeFromUser(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'is_active' => false,
        ]);
    }

    /**
     * Obtém a cor da permissão em formato hexadecimal
     */
    public function getColorAttribute($value): string
    {
        return $value ?: '#007bff';
    }

    /**
     * Retorna as estatísticas desta permissão
     */
    public function getStats(): array
    {
        return [
            'total_users' => $this->users()->count(),
            'active_users' => $this->activeUsers()->count(),
            'total_routes' => $this->routes()->count(),
            'published_routes' => $this->routes()->where('is_published', true)->count(),
        ];
    }
}
