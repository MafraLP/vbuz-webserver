<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_plate',
        'model',
        'brand',
        'year',
        'capacity',
        'type',
        'status',
        'institution_id',
        'features',
        'last_maintenance',
        'next_maintenance',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'capacity' => 'integer',
        'features' => 'json',
        'last_maintenance' => 'date',
        'next_maintenance' => 'date',
    ];

    /**
     * Relacionamento com a instituição à qual este veículo pertence
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Relacionamento com os itinerários associados a este veículo
     */
    public function itineraries(): HasMany
    {
        return $this->hasMany(Itinerary::class);
    }

    /**
     * Verifica se o veículo está disponível (ativo e sem manutenção pendente)
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->status === 'active' &&
            (!$this->next_maintenance || $this->next_maintenance->isFuture());
    }

    /**
     * Verifica se o veículo precisa de manutenção em breve (próximos 7 dias)
     *
     * @param int $daysAhead Número de dias para verificar (padrão: 7)
     * @return bool
     */
    public function needsMaintenance(int $daysAhead = 7): bool
    {
        if (!$this->next_maintenance) {
            return false;
        }

        $today = now();
        $maintenanceDeadline = $today->copy()->addDays($daysAhead);

        return $this->next_maintenance->isBetween($today, $maintenanceDeadline);
    }

    /**
     * Registra uma nova manutenção para o veículo
     *
     * @param string $description Descrição da manutenção
     * @param \DateTime|string $date Data da manutenção (default: hoje)
     * @param \DateTime|string|null $nextMaintenanceDate Data da próxima manutenção
     * @return void
     */
    public function registerMaintenance(string $description, $date = null, $nextMaintenanceDate = null): void
    {
        $maintenanceDate = $date ? now()->parse($date) : now();

        $this->last_maintenance = $maintenanceDate;

        if ($nextMaintenanceDate) {
            $this->next_maintenance = now()->parse($nextMaintenanceDate);
        }

        $this->notes = ($this->notes ? $this->notes . "\n\n" : '') .
            "Manutenção em " . $maintenanceDate->format('d/m/Y') . ": " . $description;

        $this->save();
    }
}
