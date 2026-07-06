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
            $table->unsignedBigInteger('reproceso_de_id')->nullable();
        });

        Schema::create('solicitudes_reproceso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_produccion_id')->constrained('orden_produccions')->onDelete('cascade');
            $table->text('motivo');
            $table->string('estado')->default('Pendiente'); // Pendiente, Aprobado, Rechazado
            $table->string('solicitado_por_codigo');
            $table->string('solicitado_por_nombre');
            $table->unsignedBigInteger('reproceso_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_reproceso');

        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->dropColumn('reproceso_de_id');
        });
    }
};
