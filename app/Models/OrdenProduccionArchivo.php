<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenProduccionArchivo extends Model
{
    protected $table = 'orden_produccion_archivos';

    protected $fillable = [
        'orden_produccion_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by',
        'url',
        'is_missing',
    ];

    public function ordenProduccion()
    {
        return $this->belongsTo(OrdenProduccion::class, 'orden_produccion_id');
    }
}
