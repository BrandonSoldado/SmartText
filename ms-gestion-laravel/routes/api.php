<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReporteController;

/*
|--------------------------------------------------------------------------
| Rutas públicas (sin token)
|--------------------------------------------------------------------------
*/
// Aquí van tus rutas públicas actuales (login, crear usuario, etc.)


/*
|--------------------------------------------------------------------------
| Rutas privadas (requieren JWT)
|--------------------------------------------------------------------------
| El guard "api" es el mismo que usa tu schema GraphQL: @guard(with: ["api"]).
*/
Route::middleware('auth:api')->group(function () {

    // Aquí van tus rutas privadas actuales (usuarios, roles, ventas, etc.)

    // Reportes por lenguaje natural.
    // Body: { "texto": "...", "formato": "json|excel|pdf|word" }  (formato es opcional)
    Route::post('/reporte-natural', [ReporteController::class, 'generar']);
});