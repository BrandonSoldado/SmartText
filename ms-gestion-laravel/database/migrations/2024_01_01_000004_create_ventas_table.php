<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_usuario')->constrained('usuario');
            $table->decimal('total', 12, 2);
            $table->date('fecha');
            $table->time('hora');
            $table->string('estado', 50)->default('PENDIENTE');
            $table->text('motivo_cancelacion')->nullable();
        });

        Schema::create('detalle_venta', function (Blueprint $table) {
            $table->id(); // Agregado para simplicidad del ORM
            $table->foreignId('id_venta')->constrained('venta')->onDelete('cascade');
            $table->integer('id_unidad_rollo');
            $table->decimal('cantidad', 10, 2);
            $table->decimal('precio', 10, 2);
        });
    }
    public function down() { 
        Schema::dropIfExists('detalle_venta');
        Schema::dropIfExists('venta'); 
    }
};