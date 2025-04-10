<?php
	  
	  use App\Http\Controllers\Auth\AuthController;
	  use App\Http\Controllers\Auth\ProfileController;
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
			 Route::get('/', [ProfileController::class, 'getAllProfiles']);
			 Route::post('/add-profile', [ProfileController::class, 'addProfile']);
			 Route::get('/{id}', [ProfileController::class, 'getProfileById']);
			 Route::put('/{id}', [ProfileController::class, 'update']);
			 Route::delete('/{id}', [ProfileController::class, 'delete']);
			 
			 // Profile educations
			 Route::post(
				  '/{profileId}/educations',
				  [ProfileController::class, 'addEducation']
			 );
			 Route::put(
				  '/{profileId}/educations/{educationId}',
				  [ProfileController::class, 'updateEducation']
			 );
			 Route::delete(
				  '/{profileId}/educations/{educationId}',
				  [ProfileController::class, 'deleteEducation']
			 );
			 
			 // Profile experiences
			 Route::post(
				  '/{profileId}/experiences',
				  [ProfileController::class, 'addExperience']
			 );
			 Route::put(
				  '/{profileId}/experiences/{experienceId}',
				  [ProfileController::class, 'editExperience']
			 );
			 Route::delete(
				  '/{profileId}/experiences/{experienceId}',
				  [ProfileController::class, 'deleteExperience']
			 );
			 
			 // Profile documents
			 Route::post(
				  '/{profileId}/documents',
				  [ProfileController::class, 'uploadDocument']
			 );
	  });