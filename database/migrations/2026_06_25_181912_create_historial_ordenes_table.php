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
        Schema::create('historial_ordenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_produccion_id')->constrained('orden_produccions')->onDelete('cascade');
            $table->string('tipo_evento'); // 'creacion', 'cambio_estado', 'cambio_avance', 'asignacion_lider', 'solicitud_cambio', 'aprobacion_cambio', 'rechazo_cambio'
            $table->text('descripcion');
            $table->string('realizado_por_codigo');
            $table->string('realizado_por_nombre');
            $table->string('realizado_por_rol');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_ordenes');
    }
};
