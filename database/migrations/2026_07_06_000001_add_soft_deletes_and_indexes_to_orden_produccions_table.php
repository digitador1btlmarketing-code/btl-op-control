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
            $table->softDeletes();
            $table->unsignedBigInteger('parent_op_id')->nullable();
            
            $table->foreign('parent_op_id')
                ->references('id')
                ->on('orden_produccions')
                ->onDelete('set null');

            $table->index('numero_op');
            $table->index('categoria');
            $table->index('estado');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orden_produccions', function (Blueprint $table) {
            $table->dropForeign(['parent_op_id']);
            
            $table->dropIndex(['numero_op']);
            $table->dropIndex(['categoria']);
            $table->dropIndex(['estado']);
            $table->dropIndex(['created_at']);
            
            $table->dropColumn('parent_op_id');
            $table->dropSoftDeletes();
        });
    }
};
