<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioAcceso extends Model
{
    protected $table = 'usuarios_acceso';

    protected $fillable = [
        'codigo',
        'nombre',
        'apellido',
        'rol',
        'jefe_codigo',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Get the production orders created by this user.
     */
    public function ordenes()
    {
        return $this->hasMany(OrdenProduccion::class, 'creado_por_codigo', 'codigo');
    }
}
