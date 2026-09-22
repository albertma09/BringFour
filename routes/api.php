<?php

use App\Http\Controllers\Api\BuildController;
use App\Http\Controllers\Api\BuilderController;
use App\Http\Controllers\Api\FormatController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\SpeciesController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/formats', [FormatController::class, 'index']);
Route::get('/species', [SpeciesController::class, 'index']);
Route::get('/species/{slug}', [SpeciesController::class, 'show']);
Route::get('/meta/bring-rates', [MetaController::class, 'bringRates']);
Route::get('/meta/species/{slug}/matchups', [MetaController::class, 'matchups']);
Route::get('/meta/species/{slug}/behavior', [MetaController::class, 'behavior']);
Route::get('/teams', [TeamController::class, 'index']);
Route::get('/teams/cores', [TeamController::class, 'cores']);
Route::get('/teams/{id}', [TeamController::class, 'show']);
Route::get('/builder/alignments', [BuilderController::class, 'alignments']);
Route::get('/builder/partners', [BuilderController::class, 'partners']);
Route::get('/builder/species/{slug}', [BuilderController::class, 'options']);
Route::get('/builder/species/{slug}/set', [BuilderController::class, 'set']);
Route::get('/builder/species/{slug}/spread', [BuilderController::class, 'spread']);
Route::get('/build/species/{slug}/analysis', [BuildController::class, 'analysis']);
Route::get('/build/species/{slug}/threats', [BuildController::class, 'threats']);
Route::get('/build/species/{slug}/structural-partners', [BuildController::class, 'structuralPartners']);
