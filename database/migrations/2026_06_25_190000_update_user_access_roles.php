<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Deactivate or delete the universal JEFEVENTAS-PROD-2026
        DB::table('usuarios_acceso')
            ->where('codigo', 'JEFEVENTAS-PROD-2026')
            ->update(['activo' => false]);

        // Insert new administrators and sales chiefs
        $now = now();
        DB::table('usuarios_acceso')->insertOrIgnore([
            [
                'codigo' => 'ADMIN-BRANDING-2026',
                'nombre' => 'ADMIN BRANDING',
                'apellido' => null,
                'rol' => 'admin_branding',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'codigo' => 'ADMIN-PROMO-2026',
                'nombre' => 'ADMIN PROMO',
                'apellido' => null,
                'rol' => 'admin_promo',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'codigo' => 'JEFERIZO-PROD-2026',
                'nombre' => 'JEFE RIZO',
                'apellido' => null,
                'rol' => 'jefe_ventas',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'codigo' => 'JEFECAJINA-PROD-2026',
                'nombre' => 'JEFE CAJINA',
                'apellido' => null,
                'rol' => 'jefe_ventas',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('usuarios_acceso')
            ->whereIn('codigo', [
                'ADMIN-BRANDING-2026',
                'ADMIN-PROMO-2026',
                'JEFERIZO-PROD-2026',
                'JEFECAJINA-PROD-2026'
            ])
            ->delete();

        DB::table('usuarios_acceso')
            ->where('codigo', 'JEFEVENTAS-PROD-2026')
            ->update(['activo' => true]);
    }
};
