<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudCambioFecha extends Model
{
    protected $table = 'solicitudes_cambio_fecha';

    protected $fillable = [
        'orden_produccion_id',
        'fecha_actual',
        'hora_actual',
        'fecha_solicitada',
        'hora_solicitada',
        'razon_solicitud',
        'solicitado_por_codigo',
        'solicitado_por_nombre',
        'estado_solicitud',
        'fecha_solicitud',
        'aprobado_por_codigo',
        'fecha_aprobacion',
        'rechazado_por_codigo',
        'razon_rechazo',
        'fecha_rechazo',
    ];

    protected $casts = [
        'fecha_actual' => 'date:Y-m-d',
        'fecha_solicitada' => 'date:Y-m-d',
        'fecha_solicitud' => 'datetime',
        'fecha_aprobacion' => 'datetime',
        'fecha_rechazo' => 'datetime',
    ];

    public function ordenProduccion()
    {
        return $this->belongsTo(OrdenProduccion::class, 'orden_produccion_id');
    }
}
