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
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->string('creado_por_codigo')->nullable();
            $table->string('creado_por_nombre')->nullable();
            $table->string('creado_por_rol')->nullable();
        });

        // Set default values for existing production orders
        \Illuminate\Support\Facades\DB::table('orden_produccions')->update([
            'creado_por_codigo' => 'ADMIN-PROD-2026',
            'creado_por_nombre' => 'ADMINISTRADOR',
            'creado_por_rol' => 'admin',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->dropColumn(['creado_por_codigo', 'creado_por_nombre', 'creado_por_rol']);
        });
    }
};
