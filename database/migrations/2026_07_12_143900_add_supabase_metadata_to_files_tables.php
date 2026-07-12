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
        Schema::table('orden_produccion_archivos', function (Blueprint $table) {
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('uploaded_by')->nullable();
            $table->text('url')->nullable();
            $table->boolean('is_missing')->default(false);
        });

        Schema::table('solicitudes_reproceso', function (Blueprint $table) {
            $table->unsignedBigInteger('archivo_size')->nullable();
            $table->string('archivo_mime_type')->nullable();
            $table->string('archivo_uploaded_by')->nullable();
            $table->text('archivo_url')->nullable();
            $table->boolean('is_missing')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orden_produccion_archivos', function (Blueprint $table) {
            $table->dropColumn(['file_size', 'mime_type', 'uploaded_by', 'url', 'is_missing']);
        });

        Schema::table('solicitudes_reproceso', function (Blueprint $table) {
            $table->dropColumn(['archivo_size', 'archivo_mime_type', 'archivo_uploaded_by', 'archivo_url', 'is_missing']);
        });
    }
};
