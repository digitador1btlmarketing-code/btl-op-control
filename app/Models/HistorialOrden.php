<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialOrden extends Model
{
    protected $table = 'historial_ordenes';

    protected $fillable = [
        'orden_produccion_id',
        'tipo_evento',
        'descripcion',
        'realizado_por_codigo',
        'realizado_por_nombre',
        'realizado_por_rol',
    ];

    public function ordenProduccion()
    {
        return $this->belongsTo(OrdenProduccion::class, 'orden_produccion_id');
    }
}
