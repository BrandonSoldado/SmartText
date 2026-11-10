<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_rol')->constrained('rol');
            $table->string('nombres', 100);
            $table->string('apellido_p', 100)->nullable();
            $table->string('apellido_m', 100)->nullable();
            $table->string('email', 150)->unique();
            $table->string('passwords', 255);
            $table->text('foto')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('stripe_customer_id', 100)->nullable();
        });
    }
    public function down() { Schema::dropIfExists('usuario'); }
};