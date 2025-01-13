<?php

use App\Http\Controllers\WebhookController;
use App\Http\Middleware\WebhookMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('webhook')->middleware(WebhookMiddleware::class)->group(function(){
    Route::post('client-created', [WebhookController::class, 'clientCreated']);
    Route::post('order-created', [WebhookController::class, 'orderCreated']);
    Route::post('order-updated', [WebhookController::class, 'orderUpdated']);
});