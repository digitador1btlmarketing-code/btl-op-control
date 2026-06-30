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
        'creado_por_codigo',
        'creado_por_nombre',
        'creado_por_rol',
    ];

    protected $attributes = [
        'estado' => 'Pendiente',
        'avance' => 0,
    ];

    protected $appends = [
        'dias_restantes',
        'prioridad',
        'mostrar_fuego',
        'creado_por_jefe_codigo',
    ];

    protected $casts = [
        'fecha_entrega' => 'date:Y-m-d',
        'fecha_instalacion' => 'date:Y-m-d',
        'fecha_desinstalacion' => 'date:Y-m-d',
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
            } elseif ($orden->estado === 'En espera') {
                $orden->avance = $orden->avance ?? 0;
            }
        });
    }

    /**
     * Get days remaining until delivery.
     */
    public function getDiasRestantesAttribute()
    {
        if (!$this->fecha_entrega) {
            return null;
        }
        try {
            $fechaEntrega = Carbon::parse($this->fecha_entrega)->startOfDay();
            $hoy = Carbon::today();
            return (int) $hoy->diffInDays($fechaEntrega, false);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get priority based on days remaining.
     */
    public function getPrioridadAttribute()
    {
        if (!$this->fecha_entrega) {
            return 'Sin fecha';
        }
        if ($this->estado === 'Terminado' || $this->estado === 'Cancelado') {
            return 'NORMAL';
        }
        $dias = $this->dias_restantes;
        if ($dias === null) {
            return 'Sin fecha';
        }
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
        $dias = $this->dias_restantes;
        if ($dias === null) {
            return false;
        }
        return $dias < 3;
    }

    /**
     * Get the code of the creator's jefe.
     */
    public function getCreadoPorJefeCodigoAttribute()
    {
        $creator = \App\Models\UsuarioAcceso::where('codigo', $this->creado_por_codigo)->first();
        return $creator ? $creator->jefe_codigo : null;
    }

    /**
     * Get the history records for the order.
     */
    public function historial()
    {
        return $this->hasMany(HistorialOrden::class, 'orden_produccion_id')->orderBy('created_at', 'desc');
    }

    /**
     * Get the date change requests for the order.
     */
    public function solicitudesCambio()
    {
        return $this->hasMany(SolicitudCambioFecha::class, 'orden_produccion_id');
    }

    /**
     * Get the active pending date change request.
     */
    public function solicitudPendiente()
    {
        return $this->hasOne(SolicitudCambioFecha::class, 'orden_produccion_id')->where('estado_solicitud', 'Pendiente');
    }
}
