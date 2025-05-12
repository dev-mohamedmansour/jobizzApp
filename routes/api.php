<?php
	  
	  use App\Http\Controllers\Admin\AdminAuthController;
	  use App\Http\Controllers\Admin\ApplicationController;
	  use App\Http\Controllers\Admin\CompanyController;
	  use App\Http\Controllers\Admin\JobController;
	  use App\Http\Controllers\Admin\UserController;
	  use App\Http\Controllers\Auth\AuthController;
	  use App\Http\Controllers\Auth\FavoriteController;
	  use App\Http\Controllers\Auth\ProfileController;
	  use App\Http\Controllers\FirebasePushController;
	  use App\Http\Controllers\Main\CategoryController;
	  use Illuminate\Support\Facades\Route;
	  
	  Route::prefix('admin')->group(function () {
			 // Public routes
			 Route::withoutMiddleware('admin')->group(function () {
					Route::post(
						 '/AddSuperAdmin/{id}',
						 [AdminAuthController::class, 'superAdminSignUp']
					);
					Route::post('/register', [AdminAuthController::class, 'register']
					);
					Route::post(
						 '/verify-email', [AdminAuthController::class, 'verifyEmail']
					);
					Route::post('/login', [AdminAuthController::class, 'login']);
					//password Logic
					Route::post(
						 '/password/reset-request',
						 [AdminAuthController::class, 'requestPasswordReset']
					);
					Route::post(
						 '/password/verify-pin',
						 [AdminAuthController::class, 'checkResetPasswordPinCode']
					);
			 });
			 // Authenticated routes
			 Route::middleware('admin')->group(function () {
					Route::post(
						 '/password/new-password',
						 [AdminAuthController::class, 'newPassword']
					)->middleware('check.reset.token');
					Route::prefix('users')->group(function () {
						  Route::get(
								'/',
								[UserController::class,
								 'index']
						  );
						  Route::delete(
								'/{id}',
								[UserController::class,
								 'destroy']
						  );
					});
					Route::post('/logout', [AdminAuthController::class, 'logout']);
					// Company routes
					Route::prefix('companies')->group(function () {
						  Route::post(
								'/add-company', [CompanyController::class, 'store']
						  );
						  Route::get('/', [CompanyController::class, 'index']);
						  Route::get(
								'/{companyId}', [CompanyController::class, 'show']
						  );
						  Route::put(
								'/{companyId}', [CompanyController::class, 'update']
						  );
						  Route::delete(
								'/{companyId}',
								[CompanyController::class, 'destroy']
						  );
					});
					// Job routes
					Route::prefix('jobs')->group(function () {
						  Route::post(
								'/add-job', [JobController::class, 'store']
						  );
						  Route::get('/{jobId}', [JobController::class, 'show']
						  );
						  Route::get(
								'/company/{companyId}',
								[JobController::class, 'getAllJobsForCompany']
						  );
						  Route::get('/', [JobController::class, 'index']
						  );
						  Route::put(
								'/{jobId}', [JobController::class, 'update']
						  );
						  Route::delete(
								'/{jobId}', [JobController::class, 'destroy']
						  );
					});
					Route::prefix('applications')->group(function () {
						  Route::get(
								'/',
								[ApplicationController::class,
								 'index']
						  );
						  Route::put(
								'/{applicationId}/status',
								[ApplicationController::class,
								 'updateStatus']
						  );
						  Route::get(
								'/cancelled',
								[ApplicationController::class,
								 'cancelledApplicationsForAdmin']
						  );
						  Route::put(
								'/restore/{applicationId}',
								[ApplicationController::class,
								 'restore']
						  );
						  Route::delete(
								'/{applicationId}',
								[ApplicationController::class,
								 'destroy']
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
					Route::prefix('categories')->group(function () {
						  Route::get('/', [CategoryController::class, 'index']
						  );
						  Route::get(
								'/{categoryId}',
								[CategoryController::class, 'show']
						  );
						  Route::post(
								'/add-category',
								[CategoryController::class, 'store']
						  );
						  Route::put(
								'/{categoryId}',
								[CategoryController::class, 'update']
						  );
						  Route::delete(
								'/{categoryId}',
								[CategoryController::class, 'destroy']
						  );
					});
			 });
			 
	  });
	  Route::prefix('auth')->group(function () {
			 Route::withoutMiddleware('api')->group(function () {
					// Regular auth
					Route::post('/register', [AuthController::class, 'register']);
					Route::post(
						 '/verify-email', [AuthController::class, 'verifyEmail']
					);
					Route::post(
						 '/resend-email-verification',
						 [AuthController::class, 'resendEmail']
					);
					Route::post('/login', [AuthController::class, 'login']);
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
			 });
			 Route::middleware('api')->group(function () {
					Route::post('/logout', [AuthController::class, 'logout']);
					Route::post(
						 '/fcm-token',
						 [FirebasePushController::class, 'registerToken']
					);
					Route::get('/home', [AuthController::class, 'home']);
					Route::post(
						 '/password/reset', [AuthController::class, 'newPassword']
					)->middleware('check.reset.token');
					Route::post(
						 '/password/change-password',
						 [AuthController::class, 'changePassword']
					);
					
					Route::prefix('profiles')->group(
						 function () {
								Route::get(
									 '/', [ProfileController::class, 'getAllProfiles']
								);
								Route::post(
									 '/add-profile',
									 [ProfileController::class, 'addProfile']
								);
								Route::get(
									 '/{profileId}',
									 [ProfileController::class, 'getProfileById']
								);
								Route::put(
									 '/{profileId}',
									 [ProfileController::class, 'updateProfile']
								);
								Route::delete(
									 '/{profileId}',
									 [ProfileController::class, 'deleteProfile']
								);
								// Profile educations
								Route::post(
									 '/{profileId}/educations/add-education',
									 [ProfileController::class, 'addEducation']
								);
								Route::get(
									 '/{profileId}/educations/{educationId}',
									 [ProfileController::class, 'getEducationById']
								);
								Route::get(
									 '/{profileId}/educations',
									 [ProfileController::class, 'getAllEducations']
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
									 '/{profileId}/experiences/add-experience',
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
								Route::get(
									 '/{profileId}/experiences/{experienceId}',
									 [ProfileController::class, 'getExperienceById']
								);
								Route::get(
									 '/{profileId}/experiences',
									 [ProfileController::class, 'getAllExperiences']
								);
								// Profile documents
								// CV Routes
								Route::post(
									 '/{profileId}/cvs',
									 [ProfileController::class, 'uploadCV']
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
												 [ProfileController::class,
												  'addPortfolioTypeImages']
											);
											Route::post(
												 '/pdf',
												 [ProfileController::class,
												  'addPortfolioTypePdf']
											);
											Route::post(
												 '/url',
												 [ProfileController::class,
												  'addPortfolioTypeLink']
											);
											Route::put(
												 '/images/{portfolioId}',
												 [ProfileController::class,
												  'editPortfolioImages']
											);
											Route::put(
												 '/pdf/{portfolioId}',
												 [ProfileController::class,
												  'editPortfolioPdf']
											);
											Route::put(
												 '/url/{portfolioId}',
												 [ProfileController::class,
												  'editPortfolioUrl']
											);
											Route::delete(
												 '/{portfolioId}',
												 [ProfileController::class,
												  'deletePortfolio']
											);
											Route::delete(
												 '/image/{imageId}',
												 [ProfileController::class,
												  'deletePortfolioImage']
											);
								});
						 }
					);
					Route::prefix('/profile/{profileId}/applications')->group(function () {
								Route::get(
									 '/',
									 [ApplicationController::class,
									  'getApplicationsForUser']
								);
								Route::post(
									 'add-application/{jobId}',
									 [ApplicationController::class, 'store']
								);
								Route::get(
									 '/{applicationId}/status',
									 [ApplicationController::class,
									  'getStatusHistoryForUser']
								);
						 }
					);
					Route::prefix('/profile/{profileId}/favorite')->group(function () {
								Route::get(
									 '/',
									 [FavoriteController::class,
									  'index']
								);
								Route::post(
									 '/{jobId}',
									 [FavoriteController::class, 'store']
								);
						 }
					);
					Route::prefix('companies')->group(function () {
								Route::get('/', [CompanyController::class, 'index']);
								Route::get(
									 '/{companyId}', [CompanyController::class, 'show']
								);
						 }
					);
					Route::prefix('jobs')->group(function () {
								Route::get('/', [JobController::class, 'index']
								);
								Route::get('/{jobId}', [JobController::class, 'show']
								);
						 }
					);
					Route::prefix('categories')->group(function () {
								Route::get('/', [CategoryController::class, 'index']);
								Route::get(
									 '/{categoryId}',
									 [CategoryController::class, 'show']
								);
						 }
					);
			 });
			 
	  });