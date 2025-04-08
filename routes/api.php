<?php
	  
	  use App\Http\Controllers\Auth\AuthController;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Route;
	  
	  
	  Route::prefix('auth')->group(function () {
//			 Route::get('/test-passport', function() {
//					try {
//						  $private = openssl_pkey_get_private(
//								file_get_contents(storage_path('oauth-private.key'))
//						  );
//						  return response()->json([
//								'keys_valid' => $private !== false,
//								'key_type' => $private ? openssl_pkey_get_details($private)['type'] : null
//						  ]);
//					} catch (\Exception $e) {
//						  return response()->json(['error' => $e->getMessage()], 500);
//					}
//			 });
			 // Regular auth
			 Route::post('/register', [AuthController::class, 'register']);
			 Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
			 Route::post('/login', [AuthController::class, 'login']);
			 Route::post('/logout', [AuthController::class, 'logout'])->middleware(
				  'auth:api'
			 );
			 Route::middleware('auth:api')->get(
				  '/user', function (Request $request) {
					return $request->user();
			 }
			 );
			 
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