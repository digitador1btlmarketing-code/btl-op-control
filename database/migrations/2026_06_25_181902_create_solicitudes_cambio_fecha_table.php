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
        Schema::create('solicitudes_cambio_fecha', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_produccion_id')->constrained('orden_produccions')->onDelete('cascade');
            $table->date('fecha_actual');
            $table->time('hora_actual');
            $table->date('fecha_solicitada');
            $table->time('hora_solicitada');
            $table->text('razon_solicitud');
            $table->string('solicitado_por_codigo');
            $table->string('solicitado_por_nombre');
            $table->string('estado_solicitud')->default('Pendiente'); // 'Pendiente', 'Aprobada', 'Rechazada'
            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->string('aprobado_por_codigo')->nullable();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->string('rechazado_por_codigo')->nullable();
            $table->text('razon_rechazo')->nullable();
            $table->timestamp('fecha_rechazo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_cambio_fecha');
    }
};
