<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CriteriaController;

Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{id}', [CategoryController::class, 'show']);
    Route::post('/', [CategoryController::class, 'store']);
    Route::patch('/{id}', [CategoryController::class, 'update']);
    Route::delete('/{id}', [CategoryController::class, 'destroy']);
});

Route::prefix('criterias')->group(function () {
    Route::get('/', [CriteriaController::class, 'index']);
    Route::get('/{id}', [CriteriaController::class, 'show']);
    Route::post('/', [CriteriaController::class, 'store']);
    Route::patch('/{id}', [CriteriaController::class, 'update']);
    Route::delete('/{id}', [CriteriaController::class, 'destroy']);
});
