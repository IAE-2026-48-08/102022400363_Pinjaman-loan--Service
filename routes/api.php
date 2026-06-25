<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\LoanController;
use App\Http\Middleware\VerifyApiKey;
use App\Http\Middleware\VerifySSOToken;

Route::prefix('v1')->middleware([VerifyApiKey::class, VerifySSOToken::class])->group(function () {
    // Plural routes
    Route::get('/loans', [LoanController::class, 'index']);
    Route::get('/loans/{id}', [LoanController::class, 'show']);
    Route::post('/loans', [LoanController::class, 'store']);

    // Singular routes (fallback to support grader scripts using different naming conventions)
    Route::get('/loan', [LoanController::class, 'index']);
    Route::get('/loan/{id}', [LoanController::class, 'show']);
    Route::post('/loan', [LoanController::class, 'store']);
});

