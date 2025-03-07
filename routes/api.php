<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GeminiController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::resource('products', ProductController::class);

    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::get('/{id}', [TransactionController::class, 'show']);
        Route::post('/in', [TransactionController::class, 'productIn']);
        Route::post('/out', [TransactionController::class, 'productOut']);
        Route::put('/{id}', [TransactionController::class, 'updateProduct']);
        Route::put('/{id}/cancel', [TransactionController::class, 'cancelTransaction']);
        Route::put('/{id}/approve', [TransactionController::class, 'approveTransaction']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('transaction', [ReportController::class, 'transactionReport']);
        Route::get('transaction/export-pdf', [ReportController::class, 'transactionToPdf']);
        Route::get('transaction/export-excel', [ReportController::class, 'transactionToExcel']);

        Route::get('stock', [ReportController::class, 'stockReport']);
        Route::get('stock/export-pdf', [ReportController::class, 'stockToPdf']);
        Route::get('stock/export-excel', [ReportController::class, 'stockToExcel']);
        Route::get('product/count', [ReportController::class, 'productCount']);
        Route::get('user/count', [ReportController::class, 'userCount']);
    });

    Route::prefix('gemini')->group(function () {
        Route::get('results', [GeminiController::class, 'getGeminiResults']);
        Route::get('results/latest', [GeminiController::class, 'latest']);
        Route::get('results/{result}', [GeminiController::class, 'show']);
        Route::get('results/prompt', [GeminiController::class, 'promptGemini']);
    });

    Route::get('qr-generate', [QrCodeController::class, 'generate']);
});
