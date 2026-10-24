<?php

use App\Http\Controllers\MedalleroController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MedalleroController::class, 'formulario'])->name('formulario');
Route::post('/informe', [MedalleroController::class, 'informe'])->name('informe');
Route::get('/informe', fn () => redirect()->route('formulario'));
