<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('rol', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
        });

        // Tabla intermedia
        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->foreignId('id_rol')->constrained('rol')->onDelete('cascade');
            $table->foreignId('id_permiso')->constrained('permiso')->onDelete('cascade');
            $table->primary(['id_rol', 'id_permiso']);
        });
    }
    public function down() { 
        Schema::dropIfExists('rol_permiso');
        Schema::dropIfExists('rol'); 
    }
};