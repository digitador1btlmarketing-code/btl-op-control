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
        Schema::table('solicitudes_reproceso', function (Blueprint $table) {
            $table->text('descripcion')->nullable();
            $table->date('fecha_requerida')->nullable();
            $table->string('archivo_adjunto')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitudes_reproceso', function (Blueprint $table) {
            $table->dropColumn(['descripcion', 'fecha_requerida', 'archivo_adjunto']);
        });
    }
};
