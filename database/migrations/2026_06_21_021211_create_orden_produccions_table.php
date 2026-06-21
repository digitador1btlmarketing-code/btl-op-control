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
        Schema::create('orden_produccions', function (Blueprint $table) {
            $table->id();
            $table->string('categoria');
            $table->string('numero_op');
            $table->string('proyecto');
            $table->string('presupuestista');
            $table->string('cliente');
            $table->string('marca');
            $table->date('fecha_entrega');
            $table->time('hora_entrega');
            $table->string('entregar_a');
            $table->string('lugar_instalacion')->nullable();
            $table->date('fecha_instalacion')->nullable();
            $table->time('hora_instalacion')->nullable();
            $table->date('fecha_desinstalacion')->nullable();
            $table->time('hora_desinstalacion')->nullable();
            $table->string('brief')->nullable();
            $table->string('lider_produccion')->nullable();
            $table->string('estado')->default('Pendiente');
            $table->integer('avance')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orden_produccions');
    }
};
