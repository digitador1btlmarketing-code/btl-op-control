<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudReproceso extends Model
{
    protected $table = 'solicitudes_reproceso';

    protected $fillable = [
        'orden_produccion_id',
        'motivo',
        'descripcion',
        'fecha_requerida',
        'archivo_adjunto',
        'archivo_size',
        'archivo_mime_type',
        'archivo_uploaded_by',
        'archivo_url',
        'is_missing',
        'estado',
        'solicitado_por_codigo',
        'solicitado_por_nombre',
        'reproceso_id',
        'razon_rechazo',
    ];

    public function ordenProduccion()
    {
        return $this->belongsTo(OrdenProduccion::class, 'orden_produccion_id');
    }

    public function reprocesoOrden()
    {
        return $this->belongsTo(OrdenProduccion::class, 'reproceso_id');
    }
}
