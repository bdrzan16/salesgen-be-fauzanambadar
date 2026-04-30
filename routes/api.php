<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SalesPageController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    Route::get('/sales-pages', [SalesPageController::class, 'index']);
    Route::post('/sales-pages', [SalesPageController::class, 'store']);
    Route::get('/sales-pages/{id}', [SalesPageController::class, 'show']);
    Route::put('/sales-pages/{id}', [SalesPageController::class, 'update']);
    Route::delete('/sales-pages/{id}', [SalesPageController::class, 'destroy']);
});