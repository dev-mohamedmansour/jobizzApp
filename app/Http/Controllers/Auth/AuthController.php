<?php
	  
	  namespace App\Http\Controllers\Auth;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\PasswordResetPin;
	  use App\Models\User;
	  use App\Services\PinService;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Hash;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Str;
	  use Laravel\Socialite\Facades\Socialite;
	  use Google\Client as GoogleClient;
	  use Google\Exception as GoogleException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
	  use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

//	  use Illuminate\Auth\MustVerifyEmail;
	  
	  class AuthController extends Controller
	  {
			 protected PinService $pinService;
			 
			 public function __construct(PinService $pinService)
			 {
					$this->pinService = $pinService;
			 }
			 
			 // Regular registration
			 public function register(Request $request): JsonResponse
			 {
					try {
						  // Validate the request data
						  $validated = $request->validate([
								'fullName' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
								'email'    => [
									 'required',
									 'string',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
									 'unique:users|unique:admins',
									 'ascii' // Ensures only ASCII characters
								],
								'password' => 'required|string|min:8|confirmed|regex:/^[a-zA-Z0-9@#$%^&*!]+$/'
						  ], [
								 // Custom error messages
								 'fullName.required' => 'The name field is required.',
								 'fullName.regex'    => 'Name must contain only English letters and spaces.',
								 
								 'email.required' => 'The email field is required.',
								 'email.regex'    => 'Invalid email format. Please use English characters only.',
								 'email.ascii'    => 'Email must contain only English characters.',
								 
								 'password.required'  => 'The password field is required.',
								 'password.confirmed' => 'Password confirmation does not match.',
								 'password.min'       => 'Password must be at least 8 characters.',
								 'password.regex'     => 'Password contains invalid characters. Use only English letters, numbers, and special symbols.',
						  ]);
						  
						  // Create user if validation passes
						  $user = User::create([
								'name'            => $validated['fullName'],
								'email'           => $validated['email'],
								'password'        => Hash::make($validated['password']),
								'confirmed_email' => false,
						  ]);
						  $pinResult = $this->pinService->generateAndSendPin(
								$user, 'verification'
						  );
						  
						  if (!$pinResult['email_sent']) {
								 // Optionally delete the user if email failed
								 $user->delete();
								 
								 return responseJson(
									  500,
									  'Registration Not complete because failed to send verification email'
								 );
						  }
						  
						  return responseJson(
								201,
								'Registration successful. Please check your email for verification PIN.',
								[
									 'id'       => $user->id,
									 'fullName' => $user->name,
									 'email'    => $user->email
								]
						  );
					} catch (\Illuminate\Validation\ValidationException $e) {
//						  $errors = $e->validator->errors()->all();
//						  $errorMessage = 'Validation error: ';
//						  $errorMessage .= implode(
//								'', array_map(
//									 fn($error, $index) => "$error",
//									 $errors,
//									 array_keys($errors)
//								)
//						  );
						  return responseJson(
								422,
								" validation error",
									 $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  // Handle other exceptions
						  Log::error('Server Error: ' . $e->getMessage());
						  // For production: Generic error message
						  $errorMessage
								= "Server error: Something went wrong. Please try again later.";
						  // For development: Detailed error message
						  if (config('app.debug')) {
								 $errorMessage = "Server error:\n" . $e->getMessage();
						  }
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 // Verify email with PIN
			 public function verifyEmail(Request $request): JsonResponse
			 {
					try {
						  $validated = $request->validate([
								'email'   => [
									 'required',
									 'string',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
									 'exists:users,email',
									 'ascii' // Ensures only ASCII characters
								],
								'pinCode' => [
									 'required',
									 'digits:4',
									 'numeric',
									 'not_in:0000,1111,1234,4321',
									 // Block common weak PINs
								]
						  ], [
								'email.required'   => 'The email field is required.',
								'email.regex'      => 'Invalid email format. Please use English characters only.',
								'email.ascii'      => 'Email must contain only English characters.',
								'pinCode.required' => 'PIN code is required',
								'pinCode.digits'   => 'PIN must be exactly 4 digits',
								'pinCode.numeric'  => 'PIN must contain only numbers',
								'pinCode.not_in'   => 'This PIN is too common and insecure',
						  ]);
						  // Find user without failing immediately
						  $user = User::where('email', $request['email'])->first();
						  if (!$user) {
								 return responseJson(
									  404,
									  'User not found. Please check your email or register first.'
								 );
						  }
						  // Verify PIN using your service
						  if ($this->pinService->verifyPin(
								$user, $validated['pinCode'], 'verification'
						  )
						  ) {
								 // Mark email as verified for MustVerifyEmail
								 if (!$user->hasVerifiedEmail()) {
										$user->markEmailAsVerified();
								 }

								 return responseJson(
									  200, 'Email verified successfully',
									  [
											'id'       => $user->id,
											'fullName' => $user->name,
											'email'    => $user->email
									  ]
								 );
						  }
						  return responseJson(400, 'Invalid PIN code');
					} catch (\Illuminate\Validation\ValidationException $e) {
						  $errors = $e->validator->errors()->all();
						  $errorMessage = 'Validation error: ';
						  $errorMessage .= implode(
								'', array_map(
									 fn($error, $index) => "$error",
									 $errors,
									 array_keys($errors)
								)
						  );
						  return responseJson(
								422,
								$errorMessage
						  );
					} catch (\Exception $e) {
						  // Handle other exceptions
						  Log::error('Server Error: ' . $e->getMessage());
						  // For production: Generic error message
						  $errorMessage
								= "Server error: Something went wrong. Please try again later.";
						  // For development: Detailed error message
						  if (config('app.debug')) {
								 $errorMessage = "Server error: " . $e->getMessage();
						  }
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 // Regular login
			 public function login(Request $request): JsonResponse
			 {
					try {
						  $validated = $request->validate([
								'email'    => [
									 'required',
									 'string',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
									 'email',
									 'exists:users,email', //
									 'ascii' // Ensures only ASCII characters
								],
								'password' => 'required|string|min:8|regex:/^[a-zA-Z0-9@#$%^&*!]+$/'
						  ], [
								'email.required' => 'The email field is required.',
								'email.email'    => 'Please enter a valid email address.',
								'email.exists'   => 'Account Not found in App.',
								'email.regex'    => 'Invalid email format. Please use English characters only.',
								'email.ascii'    => 'Email must contain only English characters.',
								
								'password.required' => 'The password field is required.',
								'password.min'      => 'Password must be at least 8 characters.',
								'password.regex'    => 'Password contains invalid characters. Use only English letters, numbers, and special symbols.',
						  ]);
						  // Attempt JWT authentication
						  if (!$token = JWTAuth::attempt($validated)) {
								 return responseJson(
									  401,
									  'Email Or Password Invalid',
								 );
						  }
						  $user = auth()->user();
						  // Email verification check
						  if ($user instanceof
								\Illuminate\Contracts\Auth\MustVerifyEmail
								&& !$user->hasVerifiedEmail()
						  ) {
								 return responseJson(
									  403,
									  'Please verify your email address before logging in'
								 );
						  }
						  return responseJson(
								200, 'Login successful',
								[
									 'token' => $token,
									 'id'    => $user->id,
									 'fullName'  => $user->name,
									 'email' => $user->email
								]
						  );
					} catch (\Illuminate\Validation\ValidationException $e) {
						  $errors = $e->validator->errors()->all();
						  $errorMessage = 'Validation error: ';
						  $errorMessage .= implode(
								'', array_map(
									 fn($error, $index) => "$error",
									 $errors,
									 array_keys($errors)
								)
						  );
						  return responseJson(
								422,
								$errorMessage
						  );
					} catch (\Exception $e) {
						  // Handle other exceptions
						  Log::error('Login failed: ' . $e->getMessage());
						  // For production: Generic error message
						  $errorMessage
								= "Login failed: Something went wrong. Please try again later.";
						  // For development: Detailed error message
						  if (config('app.debug')) {
								 $errorMessage = "Server error: " . $e->getMessage();
						  }
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 // Social login redirect
//			 public function redirectToProvider($provider):JsonResponse
//			 {
//					$validProviders = ['google', 'linkedin', 'github'];
//
//					if (!in_array($provider, $validProviders)) {
//						  return responseJson(400, 'Invalid provider');
//					}
//
//					$redirectUrl = url("/auth/{$provider}/callback");
//
//					config(["services.{$provider}.redirect" => $redirectUrl]);
//
//					$url = Socialite::driver($provider)
//						 ->stateless()
//						 ->redirectUrl($redirectUrl) // Explicitly set redirect URL
//						 ->redirect()
//						 ->getTargetUrl();
//
//					return responseJson(200, 'Redirect URL generated', [
//						 'redirect_url' => $url
//					]);
//			 }
//
//			 // Social login callback
//			 public function handleProviderCallback($provider): JsonResponse
//			 {
//					try {
//						  $socialUser = Socialite::driver($provider)->stateless()
//								->user();
//
//						  // Find or create user
//						  $user = User::firstOrCreate(
//								['email' => $socialUser->getEmail()],
//								[
//									 'name'              => $socialUser->getName(),
//									 'provider_id'       => $socialUser->getId(),
//									 'provider_name'     => $provider,
//									 'password'          => Hash::make(Str::random(32)),
//									 'confirmed_email'   => 1,
//									 'email_verified_at' => now() // Mark as verified
//								]
//						  );
//
//						  // Generate JWT token
//						  $token = JWTAuth::fromUser($user, [
//								'provider' => $provider,
//						  ]);
//
//						  return responseJson(200, 'Login successful', [
//								'access_token' => $token,
//								'token_type'   => 'bearer',
//								'expires_in'   => auth()->factory()->getTTL() * 60,
//								'user'         => [
//									 'id'       => $user->id,
//									 'name'     => $user->name,
//									 'email'    => $user->email,
//									 'provider' => $provider
//								]
//						  ]);
//
//					} catch (\Exception $e) {
//						  Log::error("Social auth failed: " . $e->getMessage());
//						  return responseJson(
//								401, 'Authentication failed. Please try again.'
//						  );
//					}
//			 }
			 
			 public function socialLogin(Request $request): JsonResponse
			 {
					try {
						  $validProviders = ['google'];
						  $provider = $request->input('provider');
						  $token = $request->input('token');
						  
						  // Validate inputs
						  if (!in_array($provider, $validProviders)) {
								 return responseJson(400, 'Invalid provider. Supported: google');
						  }
						  
						  if (empty($token)) {
								 return responseJson(400, 'Authorization token is required');
						  }
						  
						  // Verify social token
						  $socialUser = $this->verifySocialToken($provider, $token);
						  
						  // Find or create user
						  $user = User::firstOrCreate(
								['email' => $socialUser['email']],
								[
									 'name' => $socialUser['name'],
									 'provider_id' => $socialUser['id'],
									 'provider_name' => $provider,
									 'password' => Hash::make(Str::random(32)),
									 'email_verified_at' => now(),
									 'confirmed_email' => true,
								]
						  );
						  
						  // Generate JWT token
						  $jwtToken = JWTAuth::fromUser($user, [
								'provider' => $provider,
								'exp' => now()->addHours(2)->timestamp
						  ]);
						  
						  return responseJson(200, 'Login successful', [
								'token' => $jwtToken,
									 'id' => $user->id,
									 'fullName' => $user->name,
									 'email' => $user->email,
									 'provider' => $provider
						  ]);
						  
					} catch (\Exception $e) {
						  Log::error("Social auth failed: " . $e->getMessage());
						  return responseJson(401, 'Authentication failed: ' . $e->getMessage());
					}
			 }
			 
			 private function verifySocialToken(string $provider, string $token): array
			 {
					if ($provider === 'google') {
						  $client = new GoogleClient(['client_id' => env('GOOGLE_CLIENT_ID')]);
						  
						  try {
								 $payload = $client->verifyIdToken($token);
								 if (!$payload || $payload['aud'] !== env('GOOGLE_CLIENT_ID')) {
										throw new \Exception('Invalid Google token');
								 }
								 
								 return [
									  'id' => $payload['sub'],
									  'name' => $payload['name'] ?? '',
									  'email' => $payload['email']
								 ];
						  } catch (GoogleException $e) {
								 throw new \Exception('Google authentication failed');
						  }
					}
					
					throw new \Exception('Unsupported provider');
			 }
			 
			 // Logout
			 public function logout(): JsonResponse
			 {
					try {
						  $user = auth()->user();
						  
						  if (!$user) {
								 return responseJson(
									  401, 'No authenticated user found'
								 );
						  }
						  
						  auth()->logout();
						  
						  // Optional: Add token invalidation if using JWT
						  JWTAuth::invalidate(JWTAuth::getToken());
						  
						  // Optional: Clear session data
						  session()->flush();
						  
						  return responseJson(200, 'Successfully logged out');
						  
					} catch (TokenInvalidException $e) {
						  return responseJson(401, 'Invalid authentication token');
						  
					} catch (JWTException $e) {
						  return responseJson(500, 'Could not invalidate token');
						  
					} catch (\Exception $e) {
						  Log::error('Logout Error: ' . $e->getMessage());
						  return responseJson(
								500, 'Logout failed due to server error'
						  );
					}
			 }
			 
			 //Password Logic
			 public function requestPasswordReset(Request $request): JsonResponse
			 {
					try {
						  $validated = $request->validate([
								'email' => [
									 'required',
									 'string',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
									 'email',
									 'exists:users,email',
									 'ascii' // Ensures only ASCII characters
								]
						  ], [
								'email.required' => 'The email field is required.',
								'email.email'    => 'Please enter a valid email address.',
								'email.exists'   => 'This Account Not found in App.',
								'email.regex'    => 'Invalid email format. Please use English characters only.',
								'email.ascii'    => 'Email must contain only English characters.',
						  ]);
						  
						  $user = User::where('email', $validated['email'])->first();
						  
						  // Delete existing pins
						  PasswordResetPin::where('email', $validated['email'])
								->delete();
						  
						  // Attempt to generate and send PIN
						  $pinResult = $this->pinService->generateAndSendPin(
								$user, 'reset'
						  );
						  
						  if (!$pinResult['email_sent']) {
								 throw new \Exception(
									  'Failed to send password reset email'
								 );
						  }
						  
						  return responseJson(200, "Reset PIN sent successfully to email");
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
						  $errors = $e->validator->errors()->all();
						  $errorMessage = "Please check your email address, "
								. implode(" ", $errors);
						  
						  return responseJson(422, $errorMessage);
						  
					} catch (\Exception $e) {
						  $errorMessage
								= 'Password reset request failed. Please try again later.';
						  
						  if (config('app.debug')) {
								 $errorMessage .= "\n" . $e->getMessage();
						  }
						  
						  Log::error('Password Reset Error: ' . $e->getMessage());
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 public function checkResetPasswordPinCode(Request $request
			 ): JsonResponse {
					try {
						  $validated = $request->validate([
								'email'   => [
									 'required',
									 'string',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
									 'email',
									 'exists:users,email',
									 'ascii' // Ensures only ASCII characters
								],
								'pinCode' => [
									 'required',
									 'digits:4',
									 'numeric',
									 'not_in:0000,1111,1234,4321',
									 // Block common weak PINs
								]
						  ], [
								'email.required'   => 'The email field is required.',
								'email.regex'      => 'Invalid email format. Please use English characters only.',
								'email.ascii'      => 'Email must contain only English characters.',
								'pinCode.required' => 'PIN code is required',
								'pinCode.digits'   => 'PIN must be exactly 4 digits',
								'pinCode.numeric'  => 'PIN must contain only numbers',
								'pinCode.not_in'   => 'This PIN is too common and insecure',
						  ]);
						  //								'password' => 'required|string|min:8|confirmed'
//						  ], [
//								'email.required'    => 'User email is required',
//								'email.exists'      => 'No User account found with this email',
//								'pin.required'      => 'Verification PIN is required',
//								'pin.digits'        => 'PIN must be a 4-digit number',
////								'password.required' => 'New password is required'
//						  ]);
						  $user = User::where('email', $validated['email'])->first();
						  // Verify PIN through PinService
						  if (!$this->pinService->verifyPin(
								$user, $validated['pinCode'], 'reset'
						  )
						  ) {
								 return responseJson(
									  401, 'Expired PIN, Request a new PIN'
								 );
						  }
						  $token = JWTAuth::claims([
								'purpose' => 'password_reset',
								'exp'     => now()->addMinutes(5)->timestamp
						  ])->fromUser($user);
						  
						  // Clear reset PIN record
						  PasswordResetPin::where('email', $user->email)
								->where('type', 'user')
								->delete();
						  
						  return responseJson(
								200, 'Check Successfully'
								, ['token' => $token]
						  );
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
						  $errorMessage = "Validation failed:\n" . implode("\n", $e->validator->errors()->all());
						  return responseJson(422, $errorMessage);
						  
					} catch (\Exception $e) {
						  $message = config('app.debug')
								? "Check failed:\n" . $e->getMessage()
								: "Check failed. Please try again later";
						  
						  return responseJson(500, $message);
					}
			 }
			 
			 public function newPassword(Request $request): JsonResponse
			 {
					try {
						  // Verify token claims
						  $payload = auth()->payload();
						  
						  if ($payload->get('purpose') !== 'password_reset') {
								 return responseJson(
									  401,
									  "This token has expire"
								 );
						  }
						  // Validate new password
						  $validated = $request->validate([
								'newPassword' => 'required|string|min:8|confirmed|regex:/^[a-zA-Z0-9@#$%^&*!]+$/'
						  ], [
								
								'newPassword.required'  => 'New password is required.',
								'newPassword.confirmed' => 'Password confirmation does not match.',
								'newPassword.min'       => 'Password must be at least 8 characters.',
								'newPassword.regex'     => 'Password contains invalid characters. Use only English letters, numbers, and special symbols.',
						  ]);
						  // Update password
						  $user = auth()->user();
						  $user->password = Hash::make($validated['newPassword']);
						  $user->save();
						  // Invalidate token
						  auth()->invalidate(true);
						  auth()->logout();
						  // Optional: Add token invalidation if using JWT
						  JWTAuth::invalidate(JWTAuth::getToken());
						  // Optional: Clear session data
						  session()->flush();
						  return responseJson(200, 'Your Password updated successfully');
					} catch (\Illuminate\Validation\ValidationException $e) {
						  $errors = implode(" ", $e->validator->errors()->all());
						  return responseJson(422, "Validation failed: " . $errors);
						  
					} catch (TokenExpiredException $e) {
						  return responseJson(401, "Token expired Your session has expired");
						  
					} catch (\Exception $e) {
						  Log::error('Password Reset Error: ' . $e->getMessage());
						  $message = config('app.debug')
								? "Server error: " . $e->getMessage()
								: "An unexpected error occurred. Please try again later.";
						  return responseJson(500, $message);
					}
			 }


//			 public function verifyResetPin(Request $request):JsonResponse
//			 {
//					try {
//						  $validated = $request->validate([
//								'email' => 'required|email|exists:users,email',
//								'pinCode'   => 'required|digits:4'
//						  ], [
//								'email.required' => 'The email field is required.',
//								'email.email'    => 'Please enter a valid email address.',
//								'email.exists'   => 'No account found with this email address.',
//								'pinCode.required'   => 'The PIN code is required.',
//								'pinCode.digits'     => 'The PIN must be a 4-digit number.'
//						  ]);
//
//						  // Verify PIN
//						  $pinRecord = PasswordResetPin::where(
//								'email', $validated['email']
//						  )
//								->where('pin', $validated['pinCode'])
//								->where('created_at', '>', now()->subHours(1))
//								->first();
//
//						  if (!$pinRecord) {
//								 return responseJson(400, 'Invalid or expired PIN', [
//									  'suggestion' => 'Please request a new PIN'
//								 ]);
//						  }
//
//						  // Generate JWT token
//						  $user = User::where('email', $validated['email'])->first();
//						  $token = JWTAuth::claims([
//								'purpose' => 'password_reset',
//								'reset_id' => $pinRecord->id,
//								'exp' => now()->addMinutes(30)->timestamp
//						  ])->fromUser($user);
//
//						  // Invalidate the PIN after successful verification
//						  $pinRecord->update([
//								'pin'        => null,
//								'created_at' => null
//						  ]);
//
//						  return responseJson(200, 'PIN verified successfully', [
//								'access_token' => $token,
//								'token_type'   => 'bearer',
//								'expires_in'   => config('jwt.reset_token_ttl', 1800)
//						  ]);
//
//					} catch (ValidationException $e) {
//						  return responseJson(422, 'Validation failed', [
//								'errors' => $e->errors()
//						  ]);
//					} catch (\Exception $e) {
//						  return responseJson(500, 'PIN verification failed', [
//								'error' => config('app.debug') ? $e->getMessage() : null
//						  ]);
//					}
//			 }

//			 public function resetPassword(Request $request)
//			 {
//					try {
//						  // Verify token purpose
//						  $payload = auth()->payload();
//						  if ($payload->get('purpose') !== 'password_reset') {
//								 return responseJson(401, "Invalid token purpose\nThis token cannot be used for password reset");
//						  }
//
//						  // Validate new password
//						  $validated = $request->validate([
//								'new_password' => 'required|min:8|confirmed'
//						  ], [
//								'new_password.required'  => 'New password is required',
//								'new_password.min'       => 'Password must be at least 8 characters',
//								'new_password.confirmed' => 'Password confirmation does not match'
//						  ]);
//
//						  // Update password
//						  $user = auth()->user();
//						  $user->password = Hash::make($validated['new_password']);
//						  $user->save();
//
//						  // Invalidate the token
//						  auth()->invalidate(true);
//
//						  return responseJson(200, 'Password reset successfully');
//
//					} catch (\Illuminate\Validation\ValidationException $e) {
//						  $errors = implode('', $e->errors());
//						  return responseJson(422, "Validation errors:" . $errors);
//
//					} catch (TokenInvalidException $e) {
//						  return responseJson(401, "Invalid token :This password reset link has expired or is invalid");
//
//					} catch (\Exception $e) {
//						  Log::error('Password Reset Error: ' . $e->getMessage());
//						  $message = config('app.debug')
//								? "Password reset failed: " . $e->getMessage()
//								: "Password reset failed. Please try again later.";
//
//						  return responseJson(500, $message);
//					}
//			 }
	  }