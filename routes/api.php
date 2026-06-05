<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\LoanController;

Route::prefix('v1')->group(function () {
    Route::get('/loans', [LoanController::class, 'index']);
    Route::get('/loans/{id}', [LoanController::class, 'show']);
    Route::post('/loans', [LoanController::class, 'store']);
});
