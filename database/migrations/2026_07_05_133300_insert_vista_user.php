<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        DB::table('usuarios_acceso')->insertOrIgnore([
            [
                'codigo' => 'VISTA-PROD-2026',
                'nombre' => 'USUARIO',
                'apellido' => 'VISTA',
                'rol' => 'vista',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('usuarios_acceso')->where('codigo', 'VISTA-PROD-2026')->delete();
    }
};
