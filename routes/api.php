<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CriteriaController;
use App\Http\Controllers\Api\PerformanceController;
use App\Http\Controllers\Api\PeriodController;
use App\Http\Controllers\Api\TopsisController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/force-change-password', [AuthController::class, 'forceChangePassword']);
    Route::post('/auth/update-password', [AuthController::class, 'updatePassword']);

    Route::middleware('role:admin')->group(function () {
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('/{id}', [UserController::class, 'show']);
            Route::post('/', [UserController::class, 'store']);
            Route::patch('/{id}', [UserController::class, 'update']);
            Route::patch('/{id}/reset-password', [UserController::class, 'resetPassword']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
        });

        Route::prefix('periods')->group(function () {
            Route::get('/', [PeriodController::class, 'index']);
            Route::get('/{id}', [PeriodController::class, 'show']);
            Route::post('/', [PeriodController::class, 'store']);
            Route::patch('/{id}', [PeriodController::class, 'update']);
            Route::delete('/{id}', [PeriodController::class, 'destroy']);
        });

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

        Route::prefix('performances')->group(function () {
            Route::get('/', [PerformanceController::class, 'index']);
            Route::get('/form-options', [PerformanceController::class, 'formOptions']);
            Route::get('/group', [PerformanceController::class, 'group']);
            Route::post('/', [PerformanceController::class, 'store']);
            Route::patch('/group', [PerformanceController::class, 'update']);
            Route::delete('/group', [PerformanceController::class, 'destroy']);
        });

        Route::prefix('topsis')->group(function () {
            Route::get('/preview', [TopsisController::class, 'preview']);
            Route::post('/calculate', [TopsisController::class, 'calculate']);
            Route::delete('/period/{periodId}', [TopsisController::class, 'destroyByPeriod']);
        });
    });

    Route::middleware('role:admin,user')->group(function () {
        Route::get('/periods', [PeriodController::class, 'index']);
    });

    Route::middleware('role:admin,user')->prefix('topsis')->group(function () {
        Route::get('/', [TopsisController::class, 'index']);
        Route::get('/period/{periodId}', [TopsisController::class, 'show']);
    });
});
