<?php
	  
	  use App\Http\Controllers\Auth\AuthController;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Route;
	  
	  
	  Route::prefix('auth')->group(function () {
			 // Regular auth
			 Route::post('/register', [AuthController::class, 'register']);
			 Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
			 Route::post('/login', [AuthController::class, 'login']);
			 
			 
			 Route::middleware(['auth:api'])->group(function () {
					Route::post('/logout', [AuthController::class, 'logout']);
//					Route::get('/user', [AuthController::class, 'getAuthenticatedUser']);
					Route::post('/refresh', [AuthController::class, 'refresh']);
			 });
			 
			 // Social auth
			 Route::get(
				  '/{provider}', [AuthController::class, 'redirectToProvider']
			 );
			 Route::get(
				  '/{provider}/callback',
				  [AuthController::class, 'handleProviderCallback']
			 );
			 
			 Route::post(
				  '/password/reset-request',
				  [AuthController::class, 'requestPasswordReset']
			 );
			 Route::post(
				  '/password/verify-pin', [AuthController::class, 'verifyResetPin']
			 );
			 Route::post(
				  '/password/reset', [AuthController::class, 'resetPassword']
			 );
	  });