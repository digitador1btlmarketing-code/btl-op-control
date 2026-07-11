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
            $table->index('creado_por_codigo');
            $table->index('reproceso_de_id');
            $table->index('parent_op_id');
            $table->index('cliente');
            $table->index('marca');
        });

        Schema::table('usuarios_acceso', function (Blueprint $table) {
            $table->index('jefe_codigo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->dropIndex(['creado_por_codigo']);
            $table->dropIndex(['reproceso_de_id']);
            $table->dropIndex(['parent_op_id']);
            $table->dropIndex(['cliente']);
            $table->dropIndex(['marca']);
        });

        Schema::table('usuarios_acceso', function (Blueprint $table) {
            $table->dropIndex(['jefe_codigo']);
        });
    }
};
