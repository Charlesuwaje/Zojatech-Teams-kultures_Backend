<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
        // public routes

        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::post('/forgetPassword', [AuthController::class, 'forgetPassword']);
        Route::post('/passwordReset', [AuthController::class, 'passwordReset']); 
       
       
           // protected or private routes.
 
      Route::group(['middleware'=>['auth:sanctum']],function (){ 
  
    Route::get('/Verifyuser', [AuthController::class, 'verifyToken'])->name('verification.verify')->middleware('auth:sanctum');  
    Route::post('/status/{id}', [AuthController::class, 'status']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/CodeCheck', [AuthController::class, 'codeCheck']);
    // Route::post('/PasswordResetOpt', [AuthController::class, 'PasswordResetOpt']);
    Route::get('/search', [AuthController::class, 'search']); 
    Route::get('/filter', [AuthController::class, 'filter']);
    Route::post('/upload', [AuthController::class, 'fileupload']);
    Route::post('/create', [AuthController::class, 'create']);
    Route::post('/update/{id}', [AuthController::class, 'update']);
    Route::post('/paystack-pay', [AuthController::class, 'redirectToGateway'])->name('pay');
Route::get('/payment/callback', [AuthController::class, 'handleGatewayCallback'])->name('payment.callback');
// Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verify'])->name('verification.verify')->middleware('auth:sanctum');
    



 });

