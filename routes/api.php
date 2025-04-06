<?php
	  
	  use App\Http\Controllers\Auth\AuthController;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Route;
	  
	  Route::middleware('auth:api')->get('/user', function (Request $request) {
			 return $request->user();
	  });
	  
	  Route::prefix('auth')->group(function () {
			 // Regular auth
			 Route::post('/register', [AuthController::class, 'register']);
			 Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
			 Route::post('/login', [AuthController::class, 'login']);
			 Route::post('/logout', [AuthController::class, 'logout'])->middleware(
				  'auth:api'
			 );
			 
			 // Social auth
			 Route::get(
				  '/{provider}', [AuthController::class, 'redirectToProvider']
			 );
			 Route::get(
				  '/{provider}/callback',
				  [AuthController::class, 'handleProviderCallback']
			 );
	  });