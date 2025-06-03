<?php
	  
	  namespace App\Http\Controllers\V1\Auth;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\JobListing;
	  use App\Models\PasswordResetPin;
	  use App\Models\Profile;
	  use App\Models\User;
	  use App\Services\PinService;
	  use Google\Client as GoogleClient;
	  use Illuminate\Database\Eloquent\ModelNotFoundException;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Cache;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Hash;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Validator;
	  use Illuminate\Support\Str;
	  use Illuminate\Validation\ValidationException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
	  use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
	  
	  class UserAuthController extends Controller
	  {
			 protected PinService $pinService;
			 
			 public function __construct(PinService $pinService)
			 {
					$this->pinService = $pinService;
			 }
			 
			 /**
			  * Register a new user.
			  *
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function register(Request $request): JsonResponse
			 {
					try {
						  $validated = $this->validateRegistration($request);
						  
						  $user = DB::transaction(function () use ($validated) {
								 return User::create([
									  'name'              => $validated['fullName'],
									  'email'             => $validated['email'],
									  'password'          => Hash::make(
											$validated['password']
									  ),
									  'confirmed_email'   => true,
									  'email_verified_at' => now(),
								 ]);
						  });
						  Cache::forget('user_' . $user->id);
						  $user->profiles()->create(
								[
									 'title_job'=>'No Title',
									 'job_position'=>'No position',
									 'is_default'=>1,
									 'profile_image'=>'https://jobizaa.com/still_images/userDefault.jpg',
								]
						  );
						  
						  return responseJson(201, 'Registration successful', [
								'id'       => $user->id,
								'fullName' => $user->name,
								'email'    => $user->email,
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Register error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong. Please try again later'
						  );
					}
			 }
			 
			 /**
			  * Validate registration data.
			  *
			  * @param Request $request
			  *
			  * @return array
			  * @throws ValidationException
			  */
			 private function validateRegistration(Request $request): array
			 {
					return Validator::make($request->all(), [
						 'fullName' => ['required', 'string', 'max:255',
											 'regex:/^[a-zA-Z\s]+$/'],
						 'email'    => [
							  'required',
							  'string',
							  'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
							  'unique:users,email',
							  'unique:admins,email',
							  'ascii',
						 ],
						 'password' => ['required', 'string', 'min:8', 'confirmed',
											 'regex:/^[a-zA-Z0-9@#$%^&*!]+$/'],
					], [
						 'fullName.required'  => 'The name field is required.',
						 'fullName.regex'     => 'Name must contain only English letters and spaces.',
						 'email.required'     => 'The email field is required.',
						 'email.regex'        => 'Invalid email format.',
						 'email.ascii'        => 'Email must contain only ASCII characters.',
						 'email.unique'       => 'This email is already registered.',
						 'password.required'  => 'The password field is required.',
						 'password.confirmed' => 'Password confirmation does not match.',
						 'password.min'       => 'Password must be at least 8 characters.',
						 'password.regex'     => 'Password contains invalid characters.',
					])->validate();
			 }
			 
			 /**
			  * Resend verification email with PIN.
			  *
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function resendEmail(Request $request): JsonResponse
			 {
					try {
						  $validated = $this->validateEmail($request);
						  
						  $user = User::where('email', $validated['email'])
								->firstOrFail();
						  
						  if ($user->hasVerifiedEmail()) {
								 return responseJson(
									  400, 'Invalid request', 'Email already verified'
								 );
						  }
						  
						  $pinResult = $this->pinService->generateAndSendPin(
								$user, 'verification'
						  );
						  
						  if (!$pinResult['email_sent']) {
								 throw new \Exception(
									  'Failed to send verification email'
								 );
						  }
						  
						  return responseJson(
								200, 'Verification PIN sent',
								'Please check your email for verification PIN, including your spam folder.'
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'User not found');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Resend email error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong. Please try again later'
						  );
					}
			 }
			 
			 /**
			  * Validate email for resend and password reset.
			  *
			  * @param Request $request
			  *
			  * @return array
			  * @throws ValidationException
			  */
			 private function validateEmail(Request $request): array
			 {
					return Validator::make($request->all(), [
						 'email' => [
							  'required',
							  'string',
							  'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
							  'exists:users,email',
							  'ascii',
						 ],
					], [
						 'email.required' => 'The email field is required.',
						 'email.regex'    => 'Invalid email format.',
						 'email.ascii'    => 'Email must contain only ASCII characters.',
						 'email.exists'   => 'Email not found.',
					])->validate();
			 }
			 
			 /**
			  * Verify email with PIN and log in the user.
			  *
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function verifyEmail(Request $request): JsonResponse
			 {
					try {
						  $validated = $this->validateEmailVerification($request);
						  
						  $user = User::where('email', $validated['email'])
								->firstOrFail();
						  
						  if ($this->pinService->verifyPin(
								$user, $validated['pinCode'], 'verification'
						  )
						  ) {
								 if (!$user->hasVerifiedEmail()) {
										$user->markEmailAsVerified();
								 }
								 $profile = $user->defaultProfile()->first();
								 $token = JWTAuth::fromUser($user);
								 
								 Cache::forget('user_' . $user->id);
								 
								 return responseJson(
									  200,
									  'Email verified successfully and login successful',
									  [
											'token'    => $token,
											'id'       => $user->id,
											'fullName' => $user->name,
											'email'    => $user->email,
											'profile'   => $profile,
									  ]
								 );
						  }
						  
						  return responseJson(
								400, 'Invalid request', 'Invalid PIN code'
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'User not found');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Verify email error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong. Please try again later'
						  );
					}
			 }
			 
			 /**
			  * Validate email verification data.
			  *
			  * @param Request $request
			  *
			  * @return array
			  * @throws ValidationException
			  */
			 private function validateEmailVerification(Request $request): array
			 {
					return Validator::make($request->all(), [
						 'email'   => [
							  'required',
							  'string',
							  'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
							  'exists:users,email',
							  'ascii',
						 ],
						 'pinCode' => ['required', 'digits:6', 'numeric',
											'not_in:000000,111111,123456,654321'],
					], [
						 'email.required'   => 'The email field is required.',
						 'email.regex'      => 'Invalid email format.',
						 'email.ascii'      => 'Email must contain only ASCII characters.',
						 'email.exists'     => 'Email not found.',
						 'pinCode.required' => 'PIN code is required.',
						 'pinCode.digits'   => 'PIN must be exactly 6 digits.',
						 'pinCode.numeric'  => 'PIN must contain only numbers.',
						 'pinCode.not_in'   => 'This PIN is too common and insecure.',
					])->validate();
			 }
			 
			 /**
			  * Log in a user with email and password.
			  *
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function login(Request $request): JsonResponse
			 {
					try {
						  $validated = $this->validateLogin($request);
						  
						  if (!$token = JWTAuth::attempt($validated)) {
								 return responseJson(
									  401, 'Unauthorized', 'Invalid email or password'
								 );
						  }
						  
						  $user = auth()->user();
						  
						  if ($user instanceof
								\Illuminate\Contracts\Auth\MustVerifyEmail
								&& !$user->hasVerifiedEmail()
						  ) {
								 return responseJson(
									  403, 'Forbidden',
									  'Please verify your email address'
								 );
						  }
						  $profile=$user->defaultProfile()->first();
						  
						  return responseJson(200, 'Login successful', [
								'token'          => $token,
								'id'             => $user->id,
								'fullName'       => $user->name,
								'email'          => $user->email,
								'profile'   => $profile,

						  ]);
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Login error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong. Please try again later'
						  );
					}
			 }
			 
			 /**
			  * Validate login data.
			  *
			  * @param Request $request
			  *
			  * @return array
			  * @throws ValidationException
			  */
			 private function validateLogin(Request $request): array
			 {
					return Validator::make($request->all(), [
						 'email'    => [
							  'required',
							  'string',
							  'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
							  'exists:users,email',
							  'ascii',
						 ],
						 'password' => ['required', 'string', 'min:8',
											 'regex:/^[a-zA-Z0-9@#$%^&*!]+$/'],
					], [
						 'email.required'    => 'The email field is required.',
						 'email.regex'       => 'Invalid email format.',
						 'email.ascii'       => 'Email must contain only ASCII characters.',
						 'email.exists'      => 'Email not found.',
						 'password.required' => 'The password field is required.',
						 'password.min'      => 'Password must be at least 8 characters.',
						 'password.regex'    => 'Password contains invalid characters.',
					])->validate();
			 }
			 
			 /**
			  * Handle social login with Google.
			  *
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function socialLogin(Request $request): JsonResponse
			 {
					try {
						  $validated = $this->validateSocialLogin($request);
						  
						  $client = new GoogleClient(
								['client_id' => env('GOOGLE_CLIENT_ID')]
						  );
						  $payload = $client->verifyIdToken($validated['token']);
						  
						  if (!$payload
								|| $payload['aud'] !== env(
									 'GOOGLE_CLIENT_ID'
								)
						  ) {
								 return responseJson(
									  401, 'Unauthorized', 'Invalid Google token'
								 );
						  }
						  
						  $user = DB::transaction(function () use ($payload) {
								 return User::firstOrCreate(
									  ['email' => $payload['email']],
									  [
											'name'              => $payload['name'] ??
												 'Google User',
											'provider_id'       => $payload['sub'],
											'provider_name'     => 'google',
											'password'          => Hash::make(
												 Str::random(32)
											),
											'confirmed_email'   => true,
											'email_verified_at' => now(),
									  ]
								 );
						  });
						  
						  $token = JWTAuth::fromUser($user);
						  
						  Cache::forget('user_' . $user->id);
						  $checkUserProfile = Profile::where('user_id','=',$user->id)->get();
						  if(count($checkUserProfile) == 0){
						  $user->profiles()->create(
								[
									 'title_job'=>'No Title',
									 'job_position'=>'No position',
									 'is_default'=>1,
									 'profile_image'=>'https://jobizaa.com/still_images/userDefault.jpg',
								]
						  );
						  }
						  
						  return responseJson(200, 'Login successful', [
								'token'        => $token,
								'id'           => $user->id,
								'fullName'     => $user->name,
								'email'        => $user->email,
								'profile'   => $user->defaultProfile()->first(),
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Social login error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong. Please try again later'
						  );
					}
			 }
			 
			 /**
			  * Validate social login data.
			  *
			  * @param Request $request
			  *
			  * @return array
			  * @throws ValidationException
			  */
			 private function validateSocialLogin(Request $request): array
			 {
					return Validator::make($request->all(), [
						 'token' => ['required', 'string'],
					], [
						 'token.required' => 'The token field is required.',
					])->validate();
			 }
			 
			 /**
			  * Request a password reset PIN.
			  *
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function requestPasswordReset(Request $request): JsonResponse
			 {
					try {
						  $validated = $this->validateEmail($request);
						  
						  $user = User::where('email', $validated['email'])
								->firstOrFail();
						  
						  DB::transaction(function () use ($validated) {
								 PasswordResetPin::where('email', $validated['email'])
									  ->delete();
						  });
						  
						  $pinResult = $this->pinService->generateAndSendPin(
								$user, 'reset'
						  );
						  
						  if (!$pinResult['email_sent']) {
								 throw new \Exception(
									  'Failed to send password reset email'
								 );
						  }
						  
						  return responseJson(200, 'Reset PIN sent successfully');
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'User not found');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error(
								'Request password reset error: ' . $e->getMessage()
						  );
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong. Please try again later'
						  );
					}
			 }
			 
			 /**
			  * Verify password reset PIN.
			  *
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function checkResetPasswordPinCode(Request $request
			 ): JsonResponse {
					try {
						  $validated = $this->validatePin($request);
						  
						  $user = User::where('email', $validated['email'])
								->firstOrFail();
						  
						  if (!$this->pinService->verifyPin(
								$user, $validated['pinCode'], 'reset'
						  )
						  ) {
								 return responseJson(
									  401, 'Unauthorized', 'Invalid or expired PIN'
								 );
						  }
						  
						  $token = JWTAuth::claims([
								'purpose' => 'password_reset',
								'exp'     => now()->addMinutes(5)->timestamp,
						  ])->fromUser($user);
						  
						  DB::transaction(function () use ($user) {
								 PasswordResetPin::where('email', $user->email)->where(
									  'type', 'user'
								 )->delete();
						  });
						  
						  return responseJson(
								200, 'PIN verified successfully', ['token' => $token]
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'User not found');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Check reset PIN error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong. Please try again later'
						  );
					}
			 }
			 
			 /**
			  * Validate PIN for password reset.
			  *
			  * @param Request $request
			  *
			  * @return array
			  * @throws ValidationException
			  */
			 private function validatePin(Request $request): array
			 {
					return Validator::make($request->all(), [
						 'email'   => [
							  'required',
							  'string',
							  'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
							  'exists:users,email',
							  'ascii',
						 ],
						 'pinCode' => ['required', 'digits:6', 'numeric',
											'not_in:000000,111111,123456,654321'],
					], [
						 'email.required'   => 'The email field is required.',
						 'email.regex'      => 'Invalid email format.',
						 'email.ascii'      => 'Email must contain only ASCII characters.',
						 'email.exists'     => 'Email not found.',
						 'pinCode.required' => 'PIN code is required.',
						 'pinCode.digits'   => 'PIN must be exactly 6 digits.',
						 'pinCode.numeric'  => 'PIN must contain only numbers.',
						 'pinCode.not_in'   => 'This PIN is too common and insecure.',
					])->validate();
			 }
			 
			 /**
			  * Set a new password after PIN verification.
			  *
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function newPassword(Request $request): JsonResponse
			 {
					try {
						  $payload = auth()->payload();
						  if ($payload->get('purpose') !== 'password_reset') {
								 return responseJson(
									  401, 'Unauthorized', 'Invalid token purpose'
								 );
						  }
						  
						  $validated = $this->validateNewPassword($request);
						  
						  $user = auth()->user();
						  $user->password = Hash::make($validated['newPassword']);
						  $user->save();
						  
						  JWTAuth::invalidate(JWTAuth::getToken());
						  auth()->logout();
						  session()->flush();
						  
						  Cache::forget('user_' . $user->id);
						  
						  return responseJson(200, 'Password updated successfully');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (TokenExpiredException $e) {
						  return responseJson(
								401, 'Unauthorized', 'Token has expired'
						  );
					} catch (\Exception $e) {
						  Log::error('New password error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong. Please try again later'
						  );
					}
			 }
			 
			 /**
			  * Validate new password data.
			  *
			  * @param Request $request
			  *
			  * @return array
			  * @throws ValidationException
			  */
			 private function validateNewPassword(Request $request): array
			 {
					return Validator::make($request->all(), [
						 'newPassword' => ['required', 'string', 'min:8', 'confirmed',
												 'regex:/^[a-zA-Z0-9@#$%^&*!]+$/'],
					], [
						 'newPassword.required'  => 'New password is required.',
						 'newPassword.confirmed' => 'Password confirmation does not match.',
						 'newPassword.min'       => 'Password must be at least 8 characters.',
						 'newPassword.regex'     => 'Password contains invalid characters.',
					])->validate();
			 }
			 
			 /**
			  * Log out the authenticated user.
			  *
			  * @return JsonResponse
			  */
			 public function logout(): JsonResponse
			 {
					try {
						  $user = auth()->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthorized', 'No authenticated user found'
								 );
						  }
						  
						  JWTAuth::invalidate(JWTAuth::getToken());
						  auth()->logout();
						  session()->flush();
						  
						  Cache::forget('user_' . $user->id);
						  
						  return responseJson(200, 'Successfully logged out');
					} catch (TokenInvalidException $e) {
						  return responseJson(
								401, 'Unauthorized', 'Invalid authentication token'
						  );
					} catch (JWTException $e) {
						  return responseJson(
								500, 'Server error', 'Could not invalidate token'
						  );
					} catch (\Exception $e) {
						  Log::error('Logout error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong. Please try again later'
						  );
					}
			 }
			 
			 /**
			  * Change the authenticated user's password.
			  *
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function changePassword(Request $request): JsonResponse
			 {
					try {
						  $user = auth('api')->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthorized', 'No authenticated user found'
								 );
						  }
						  
						  $validated = $this->validateChangePassword($request);
						  
						  if (!Hash::check(
								$validated['oldPassword'], $user->password
						  )
						  ) {
								 return responseJson(
									  401, 'Unauthorized', 'Old password is incorrect'
								 );
						  }
						  
						  $user->password = Hash::make($validated['newPassword']);
						  $user->save();
						  
						  Cache::forget('user_' . $user->id);
						  
						  return responseJson(200, 'Password changed successfully');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Change password error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong. Please try again later'
						  );
					}
			 }
			 
			 /**
			  * Validate change password data.
			  *
			  * @param Request $request
			  *
			  * @return array
			  * @throws ValidationException
			  */
			 private function validateChangePassword(Request $request): array
			 {
					return Validator::make($request->all(), [
						 'oldPassword' => ['required', 'string'],
						 'newPassword' => ['required', 'string', 'min:8', 'confirmed',
												 'different:oldPassword',
												 'regex:/^[a-zA-Z0-9@#$%^&*!]+$/'],
					], [
						 'oldPassword.required'  => 'Old password is required.',
						 'newPassword.required'  => 'New password is required.',
						 'newPassword.confirmed' => 'Password confirmation does not match.',
						 'newPassword.min'       => 'Password must be at least 8 characters.',
						 'newPassword.different' => 'New password must be different from old password.',
						 'newPassword.regex'     => 'Password contains invalid characters.',
					])->validate();
			 }
			 
			 /**
			  * Retrieve job recommendations for the authenticated user.
			  *
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function home(Request $request): JsonResponse
			 {
					try {
						  $user = auth('api')->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthorized', 'Unauthenticated'
								 );
						  }
						  
						  $profile = $user->defaultProfile()->first();
						  if (!$profile) {
								 return responseJson(404, 'Not found', 'Profile not found');
						  }
						  
						  $profileJobTitle = $profile->title_job;
						  $jobsNum = Cache::remember(
								'job_count', now()->addMinutes(3), fn() => JobListing::count()
						  );
						  
						  if ($jobsNum === 0) {
								 return responseJson(
									  404, 'Not found', 'No jobs available'
								 );
						  }
						  
						  $number = max(1, (int)($jobsNum / 3));
						  
						  // Cache trending jobs for 15 minutes
						  $jobsTrending = Cache::remember(
								'jobs_trending_' . $user->id, now()->addMinutes(5),
								fn() => JobListing::inRandomOrder()
									 ->select(
										  ['id', 'title', 'company_id', 'location',
											'job_type', 'salary', 'position',
											'category_name', 'description', 'requirement',
											'benefits']
									 )
									 ->with(
										  ['company' => fn($query) => $query->select(
												['id', 'name', 'logo']
										  )]
									 )
									 ->take($number)
									 ->get()
									 ->map(function ($job) use ($profile){
											return [
												 'id'            => $job->id,
												 'title'         => $job->title,
												 'company_id'    => $job->company_id,
												 'location'      => $job->location,
												 'job_type'      => $job->job_type,
												 'salary'        => $job->salary,
												 'position'      => $job->position,
												 'category_name' => $job->category_name,
												 'description'   => $job->description,
												 'requirement'   => $job->requirement,
												 'benefits'      => $job->benefits,
												 'companyName'   => $job->company->name,
												 'companyLogo'   => $job->company->logo,
												 'isFavorite'   => $job->isFavoritedByProfile($profile->id),
											];
									 })
						  );
						  // Cache popular jobs for 15 minutes
						  $jobsPopular = Cache::remember(
								'jobs_popular_' . $user->id, now()->addMinutes(5),
								fn() => JobListing::inRandomOrder()
									 ->select(
										  ['id', 'title', 'company_id', 'location',
											'job_type', 'salary', 'position',
											'category_name', 'description', 'requirement',
											'benefits']
									 )
									 ->with(
										  ['company' => fn($query) => $query->select(
												['id', 'name', 'logo']
										  )]
									 )
									 ->take($number)
									 ->get()
									 ->map(function ($job) use ($profile){
											return [
												 'id'            => $job->id,
												 'title'         => $job->title,
												 'company_id'    => $job->company_id,
												 'location'      => $job->location,
												 'job_type'      => $job->job_type,
												 'salary'        => $job->salary,
												 'position'      => $job->position,
												 'category_name' => $job->category_name,
												 'description'   => $job->description,
												 'requirement'   => $job->requirement,
												 'benefits'      => $job->benefits,
												 'companyName'   => $job->company->name,
												 'companyLogo'   => $job->company->logo,
												 'isFavorite'   => $job->isFavoritedByProfile($profile->id)
											];
									 })
						  );
						  
						  // Cache recommended jobs for 15 minutes, based on profile job title
						  $jobsRecommended = Cache::remember(
								'jobs_recommended_' . $user->id . '_' . md5(
									 $profileJobTitle
								), now()->addMinutes(5), fn() => JobListing::where(
								'title', 'like', '%' . $profileJobTitle . '%'
						  )
								->inRandomOrder()
								->select(
									 ['id', 'title', 'company_id', 'location',
									  'job_type', 'salary', 'position', 'category_name',
									  'description', 'requirement', 'benefits']
								)
								->with(
									 ['company' => fn($query) => $query->select(
										  ['id', 'name', 'logo']
									 )]
								)
								->take($number)
								->get()
								->map(function ($job) use ($profile){
									  return [
											'id'            => $job->id,
											'title'         => $job->title,
											'company_id'    => $job->company_id,
											'location'      => $job->location,
											'job_type'      => $job->job_type,
											'salary'        => $job->salary,
											'position'      => $job->position,
											'category_name' => $job->category_name,
											'description'   => $job->description,
											'requirement'   => $job->requirement,
											'benefits'      => $job->benefits,
											'companyName'   => $job->company->name,
											'companyLogo'   => $job->company->logo,
											'isFavorite'   => $job->isFavoritedByProfile($profile->id),
									  ];
								})
						  );
						  
						  return responseJson(200, 'Jobs retrieved successfully', [
								'Trending'    => $jobsTrending,
								'Popular'     => $jobsPopular,
								'Recommended' => $jobsRecommended,
						  ]);
					} catch (\Exception $e) {
						  Log::error('Home error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong. Please try again later'
						  );
					}
			 }
			 
	  }