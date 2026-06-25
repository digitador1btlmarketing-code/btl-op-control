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
        Schema::table('usuarios_acceso', function (Blueprint $table) {
            $table->string('jefe_codigo')->nullable();
        });

        // Map existing vendedores to their respective jefes
        \Illuminate\Support\Facades\DB::table('usuarios_acceso')
            ->whereIn('codigo', [
                'DAFNE-RAMIREZ-PROD-2026',
                'FRANCIS-SANCHEZ-PROD-2026',
                'LILLIAM-HERNANDEZ-PROD-2026',
                'ANDREA-MUNOZ-PROD-2026'
            ])
            ->update(['jefe_codigo' => 'JEFERIZO-PROD-2026']);

        \Illuminate\Support\Facades\DB::table('usuarios_acceso')
            ->whereIn('codigo', [
                'CARLOS-VARGAS-PROD-2026',
                'XIMENA-MENDOZA-PROD-2026',
                'ROSMERY-GUIDO-PROD-2026'
            ])
            ->update(['jefe_codigo' => 'JEFECAJINA-PROD-2026']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usuarios_acceso', function (Blueprint $table) {
            $table->dropColumn('jefe_codigo');
        });
    }
};
