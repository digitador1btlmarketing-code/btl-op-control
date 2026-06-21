<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OrdenProduccion extends Model
{
    protected $table = 'orden_produccions';

    protected $fillable = [
        'categoria',
        'numero_op',
        'proyecto',
        'presupuestista',
        'cliente',
        'marca',
        'fecha_entrega',
        'hora_entrega',
        'entregar_a',
        'lugar_instalacion',
        'fecha_instalacion',
        'hora_instalacion',
        'fecha_desinstalacion',
        'hora_desinstalacion',
        'brief',
        'lider_produccion',
        'estado',
        'avance',
    ];

    protected static function booted()
    {
        static::saving(function ($orden) {
            if ($orden->estado === 'Pendiente') {
                $orden->avance = 0;
            } elseif ($orden->estado === 'En proceso') {
                $orden->avance = 50;
            } elseif ($orden->estado === 'Terminado') {
                $orden->avance = 100;
            } elseif ($orden->estado === 'Cancelado') {
                $orden->avance = 0;
            }
        });
    }

    /**
     * Get days remaining until delivery.
     */
    public function getDiasRestantesAttribute()
    {
        $fechaEntrega = Carbon::parse($this->fecha_entrega)->startOfDay();
        $hoy = Carbon::today();
        return (int) $hoy->diffInDays($fechaEntrega, false);
    }

    /**
     * Get priority based on days remaining.
     */
    public function getPrioridadAttribute()
    {
        if ($this->estado === 'Terminado' || $this->estado === 'Cancelado') {
            return 'NORMAL';
        }
        $dias = $this->dias_restantes;
        if ($dias <= 0) {
            return 'URGENTE';
        } elseif ($dias <= 2) {
            return 'PRÓXIMA';
        } else {
            return 'NORMAL';
        }
    }

    /**
     * Check if we should display the fire emoji.
     */
    public function getMostrarFuegoAttribute()
    {
        if ($this->estado === 'Terminado' || $this->estado === 'Cancelado') {
            return false;
        }
        return $this->dias_restantes < 3;
    }
}
