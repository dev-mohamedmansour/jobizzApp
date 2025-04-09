<?php
	  
	  use App\Http\Controllers\Auth\AuthController;
	  use App\Http\Controllers\Auth\ProfileController;
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
	  
	  Route::prefix('profiles')->middleware('auth:api')->group(function () {
			 Route::get('/', [ProfileController::class, 'index']);
			 Route::post('/', [ProfileController::class, 'store']);
			 Route::get('/{id}', [ProfileController::class, 'show']);
			 Route::put('/{id}', [ProfileController::class, 'update']);
			 Route::delete('/{id}', [ProfileController::class, 'destroy']);
			 
			 // Profile images
			 Route::post('/{profileId}/images', [ProfileController::class, 'uploadImage']);
			 
			 // Profile educations
			 Route::post('/{profileId}/educations', [ProfileController::class, 'addEducation']);
			 
			 // Profile experiences
			 Route::post('/{profileId}/experiences', [ProfileController::class, 'addExperience']);
			 
			 // Profile documents
			 Route::post('/{profileId}/documents', [ProfileController::class, 'uploadDocument']);
	  });