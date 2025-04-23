<?php
	  
	  use App\Http\Controllers\Admin\AdminAuthController;
	  use App\Http\Controllers\Admin\ApplicationController;
	  use App\Http\Controllers\Admin\CompanyController;
	  use App\Http\Controllers\Admin\JobController;
	  use App\Http\Controllers\Auth\AuthController;
	  use App\Http\Controllers\Auth\ProfileController;
	  use App\Http\Controllers\Main\CategoryController;
	  use Illuminate\Support\Facades\Route;
	  
	  
	  Route::prefix('admin')->group(function () {
			 // Public routes
			 Route::post(
				  '/AddSuperAdmin/{id}',
				  [AdminAuthController::class, 'superAdminSignUp']
			 );
			 
			 Route::post('/register', [AdminAuthController::class, 'register']);
			 Route::post(
				  '/verify-email', [AdminAuthController::class, 'verifyEmail']
			 );
			 Route::post('/login', [AdminAuthController::class, 'login']);
//			 Route::post(
//				  '/password/forgot',
//				  [AdminAuthController::class, 'forgotAdminPassword']
//			 );
//			 Route::post(
//				  '/password/reset',
//				  [AdminAuthController::class, 'resetAdminPassword']
//			 );
			 
			 //password Logic
			 Route::post(
				  '/password/reset-request',
				  [AdminAuthController::class, 'requestPasswordReset']
			 );
			 Route::post(
				  '/password/verify-pin',
				  [AdminAuthController::class, 'checkResetPasswordPinCode']
			 );
			 Route::post(
				  '/password/new-password',
				  [AdminAuthController::class, 'newPassword']
			 )->middleware(['auth:admin', 'check.reset.token']);
			 
			 // Authenticated routes
			 Route::middleware(['auth:admin'])
				  ->group(function () {
						 // Auth routes
						 Route::post('/logout', [AdminAuthController::class, 'logout']
						 );
						 // Company routes
						 Route::prefix('companies')->group(function () {
								Route::post(
									 '/add-company', [CompanyController::class, 'store']
								);
								Route::get('/', [CompanyController::class, 'index']);
								Route::get('/{id}', [CompanyController::class, 'show']);
						 });
						 // Job routes
						 Route::prefix('jobs')->group(function () {
								Route::post('/add-job', [JobController::class, 'store']
								);
								Route::get('/{job}', [JobController::class, 'show']
								);
								Route::get('/', [JobController::class, 'index']
								);
								Route::put(
									 '/update/{job}', [JobController::class, 'update']
								);
								Route::delete(
									 '/delete/{job}', [JobController::class, 'destroy']
								);
								
						 });
						 
						 // Admin management
						 Route::post(
							  '/approve/{pendingAdmin}',
							  [AdminAuthController::class, 'approve']
						 );
						 Route::post(
							  '/sub-admin',
							  [AdminAuthController::class, 'createSubAdmin']
						 );
						 //categories route
						 Route::prefix('categories')->group(
							  function () {
									 Route::get('/', [CategoryController::class, 'index']
									 );
									 Route::get(
										  '/{category}',
										  [CategoryController::class, 'show']
									 );
									 Route::post(
										  '/add-category',
										  [CategoryController::class, 'store']
									 );
									 Route::put(
										  '/{category}',
										  [CategoryController::class, 'update']
									 );
									 Route::delete(
										  '/{category}',
										  [CategoryController::class, 'destroy']
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
			 Route::post(
				  '/google-login', [AuthController::class, 'socialLogin']
			 );
			 //password Logic
			 Route::post(
				  '/password/reset-request',
				  [AuthController::class, 'requestPasswordReset']
			 );
			 Route::post(
				  '/password/verify-pin',
				  [AuthController::class, 'checkResetPasswordPinCode']
			 );
			 Route::post(
				  '/password/reset', [AuthController::class, 'newPassword']
			 )->middleware(['auth:api', 'check.reset.token']);
			 
			 
			 Route::prefix('profiles')->middleware('auth:api')->group(function () {
					Route::get('/', [ProfileController::class, 'getAllProfiles']);
					Route::post(
						 '/add-profile', [ProfileController::class, 'addProfile']
					);
					Route::get('/{id}', [ProfileController::class, 'getProfileById']
					);
					Route::put('/{id}', [ProfileController::class, 'updateProfile']);
					Route::delete(
						 '/{id}', [ProfileController::class, 'deleteProfile']
					);
					
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
					Route::post(
						 '/{profileId}/cvs', [ProfileController::class, 'uploadCV']
					);
					Route::put(
						 '/{profileId}/cvs/{cvId}',
						 [ProfileController::class, 'editCV']
					);
					Route::delete(
						 '/{profileId}/cvs/{cvId}',
						 [ProfileController::class, 'deleteCV']
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
			 
			 Route::prefix('applications')->middleware('auth:api')->group(
				  function () {
						 Route::get(
							  '/{profileId}/all',
							  [ApplicationController::class,
								'getUserProfileApplications']
						 );
						 Route::post(
							  '/{profileId}/add',
							  [ApplicationController::class, 'store']
						 );
				  }
			 );
			 Route::prefix('companies')->middleware('auth:api')->group(
				  function () {
						 Route::get('/', [CompanyController::class, 'index']);
						 Route::get('/{id}', [CompanyController::class, 'show']);
				  }
			 );
			 // Job routes
			 Route::prefix('jobs')->middleware('auth:api')->group(function () {
					
					Route::get('/', [JobController::class, 'index']
					);
					Route::get('/{job}', [JobController::class, 'show']
					);
			 });
	  });