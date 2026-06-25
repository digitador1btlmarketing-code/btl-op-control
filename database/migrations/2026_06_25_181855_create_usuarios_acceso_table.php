<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usuarios_acceso', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->string('apellido')->nullable();
            $table->string('rol');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Insert initial required access users
        $now = now();
        \Illuminate\Support\Facades\DB::table('usuarios_acceso')->insert([
            // Administrative & TV roles
            [
                'codigo' => 'ADMIN-PROD-2026',
                'nombre' => 'ADMINISTRADOR',
                'apellido' => null,
                'rol' => 'admin',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'codigo' => 'JEFEVENTAS-PROD-2026',
                'nombre' => 'JEFE DE VENTAS',
                'apellido' => null,
                'rol' => 'jefe_ventas',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'codigo' => 'BRANDING-PROD-2026',
                'nombre' => 'TV BRANDING',
                'apellido' => null,
                'rol' => 'tv_branding',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'codigo' => 'PROMO-PROD-2026',
                'nombre' => 'TV PROMOCIONAL',
                'apellido' => null,
                'rol' => 'tv_promocional',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            // Initial individual sales users
            [
                'codigo' => 'DAFNE-RAMIREZ-PROD-2026',
                'nombre' => 'DAFNE',
                'apellido' => 'RAMIREZ',
                'rol' => 'ventas',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'codigo' => 'FRANCIS-SANCHEZ-PROD-2026',
                'nombre' => 'FRANCIS',
                'apellido' => 'SANCHEZ',
                'rol' => 'ventas',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'codigo' => 'LILLIAM-HERNANDEZ-PROD-2026',
                'nombre' => 'LILLIAM',
                'apellido' => 'HERNANDEZ',
                'rol' => 'ventas',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'codigo' => 'ANDREA-MUNOZ-PROD-2026',
                'nombre' => 'ANDREA',
                'apellido' => 'MUNOZ',
                'rol' => 'ventas',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'codigo' => 'CARLOS-VARGAS-PROD-2026',
                'nombre' => 'CARLOS',
                'apellido' => 'VARGAS',
                'rol' => 'ventas',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'codigo' => 'XIMENA-MENDOZA-PROD-2026',
                'nombre' => 'XIMENA',
                'apellido' => 'MENDOZA',
                'rol' => 'ventas',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'codigo' => 'ROSMERY-GUIDO-PROD-2026',
                'nombre' => 'ROSMERY',
                'apellido' => 'GUIDO',
                'rol' => 'ventas',
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
        Schema::dropIfExists('usuarios_acceso');
    }
};
