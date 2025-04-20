<?php
	  
	  use App\Http\Controllers\Admin\AdminAuthController;
	  use App\Http\Controllers\Admin\ApplicationController;
	  use App\Http\Controllers\Admin\CompanyController;
	  use App\Http\Controllers\Admin\JobController;
	  use App\Http\Controllers\Auth\AuthController;
	  use App\Http\Controllers\Auth\ProfileController;
	  use Illuminate\Support\Facades\Route;
	  
	  
	  Route::prefix('admin')->group(function () {
			 // Public routes
			 Route::post('/register', [AdminAuthController::class, 'register']);
			 Route::post(
				  '/verify-email', [AdminAuthController::class, 'verifyEmail']
			 );
			 Route::post('/login', [AdminAuthController::class, 'login']);
			 Route::post(
				  '/password/forgot',
				  [AdminAuthController::class, 'forgotAdminPassword']
			 );
			 Route::post(
				  '/password/reset',
				  [AdminAuthController::class, 'resetAdminPassword']
			 );
			 
			 // Authenticated routes
			 Route::middleware(['auth:admin', 'verified', 'approved.admin'])
				  ->group(function () {
						 // Auth routes
						 Route::post('/logout', [AdminAuthController::class, 'logout']
						 );
						 // Company routes
						 Route::apiResource('companies', CompanyController::class)
							  ->middleware(
									'permission:manage-all-companies|manage-own-company'
							  );
						 // Job routes
						 Route::apiResource('jobs', JobController::class)
							  ->middleware(
									'permission:manage-all-jobs|manage-company-jobs'
							  );
						 
						 // Admin management
						 Route::middleware('role:super-admin|admin')->group(
							  function () {
									 Route::post(
										  '/approve/{admin}',
										  [AdminAuthController::class, 'approve']
									 );
									 Route::post(
										  '/sub-admin',
										  [AdminAuthController::class, 'createSubAdmin']
									 );
							  }
						 );
				  });
			 
	  });
	  
	  Route::prefix('auth')->group(function () {
			 // Regular auth
			 Route::post('/register', [AuthController::class, 'register']);
			 Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
			 Route::post('/login', [AuthController::class, 'login']);
			 
			 
			 Route::middleware(['auth:api'])->group(function () {
					Route::post('/logout', [AuthController::class, 'logout']);
			 });
			 
			 // Social auth
			 Route::get(
				  '/{provider}', [AuthController::class, 'redirectToProvider']
			 );
			 Route::get(
				  '/{provider}/callback',
				  [AuthController::class, 'handleProviderCallback']
			 );
			 //password Logic
			 Route::post(
				  '/password/reset-request',
				  [AuthController::class, 'requestPasswordReset']
			 );
			 Route::post(
				  '/password/verify-pin', [AuthController::class, 'checkResetPasswordPinCode']
			 );
			 Route::post(
				  '/password/reset', [AuthController::class, 'newPassword'])
				  ->middleware(['auth:api', 'check.reset.token']);
	  });
	  
	  Route::prefix('profiles')->middleware('auth:api')->group(function () {
			 Route::get('/', [ProfileController::class, 'getAllProfiles']);
			 Route::post('/add-profile', [ProfileController::class, 'addProfile']);
			 Route::get('/{id}', [ProfileController::class, 'getProfileById']);
			 Route::put('/{id}', [ProfileController::class, 'updateProfile']);
			 Route::delete('/{id}', [ProfileController::class, 'deleteProfile']);
			 
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
			 // CV Routes
			 Route::post('/{profileId}/cvs', [ProfileController::class, 'uploadCV']
			 );
			 Route::put(
				  '/{profileId}/cvs/{cvId}', [ProfileController::class, 'editCV']
			 );
			 Route::delete(
				  '/{profileId}/cvs/{cvId}', [ProfileController::class, 'deleteCV']
			 );
			 
			 // Portfolio Routes
			 Route::prefix('{profileId}/portfolio')->group(function () {
					Route::post(
						 '/images',
						 [ProfileController::class, 'addPortfolioTypeImages']
					);
					Route::post(
						 '/pdf',
						 [ProfileController::class, 'addPortfolioTypePdf']
					);
					Route::post(
						 '/url',
						 [ProfileController::class, 'addPortfolioTypeLink']
					);
					Route::put(
						 '/images/{portfolioId}',
						 [ProfileController::class, 'editPortfolioImages']
					);
					Route::put(
						 '/pdf/{portfolioId}',
						 [ProfileController::class, 'editPortfolioPdf']
					);
					Route::put(
						 '/url/{portfolioId}',
						 [ProfileController::class, 'editPortfolioUrl']
					);
					Route::delete(
						 '/{portfolioId}',
						 [ProfileController::class, 'deletePortfolio']
					);
			 });
			 
			 
	  });
	  
	  Route::prefix('applications')->middleware('auth:api')->group(function () {
			 Route::get(
				  '/{profileId}/all',
				  [ApplicationController::class, 'getUserProfileApplications']
			 );
			 Route::post(
				  '/{profileId}/add', [ApplicationController::class, 'store']
			 );
	  });
	  Route::prefix('companies')->middleware('auth:api')->group(function () {
			 Route::get('/get-all', [CompanyController::class, 'index']);
			 Route::post('/{companyId}/get', [CompanyController::class, 'show']);
	  });
