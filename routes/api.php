<?php

use App\Http\Controllers\Api\FormatController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\SpeciesController;
use Illuminate\Support\Facades\Route;

Route::get('/formats', [FormatController::class, 'index']);
Route::get('/species', [SpeciesController::class, 'index']);
Route::get('/species/{slug}', [SpeciesController::class, 'show']);
Route::get('/meta/bring-rates', [MetaController::class, 'bringRates']);
Route::get('/meta/species/{slug}/matchups', [MetaController::class, 'matchups']);
Route::get('/meta/species/{slug}/behavior', [MetaController::class, 'behavior']);
