<?php
	  
	  namespace App\Http\Controllers\Auth;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\PasswordResetPin;
	  use App\Models\User;
	  use App\Services\PinService;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Hash;
	  use Illuminate\Support\Str;
	  use Illuminate\Validation\ValidationException;
	  use Laravel\Socialite\Facades\Socialite;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenBlacklistedException;
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
			 public function register(Request $request)
			 {
					try {
						  // Validate the request data
						  $validated = $request->validate([
								'name'     => 'required|string|max:255',
								'email'    => 'required|string|email|unique:users',
								'phone'    => 'required|string|unique:users',
								'password' => 'required|string|min:8|confirmed'
						  ], [
								 // Custom error messages
								 'name.required'      => 'The name field is required.',
								 'email.required'     => 'The email field is required.',
								 'phone.required'     => 'The phone number is required.',
								 'password.required'  => 'The password field is required.',
								 'password.confirmed' => 'Password confirmation does not match.',
								 'password.min'       => 'Password must be at least 8 characters.',
						  ]);
						  
						  // Create user if validation passes
						  $user = User::create([
								'name'            => $validated['name'],
								'email'           => $validated['email'],
								'phone'           => $validated['phone'],
								'password'        => Hash::make($validated['password']),
								'confirmed_email' => false
						  ]);
						  $pinResult = $this->pinService->generateAndSendPin(
								$user, 'verification'
						  );
						  
						  if (!$pinResult['email_sent']) {
								 // Optionally delete the user if email failed
								 $user->delete();
								 
								 return responseJson(
									  500,
									  'Registration completed but failed to send verification email',
									  ['user_created' => false]
								 );
						  }
						  
						  return responseJson(
								201,
								'Registration successful. Please check your email for verification PIN.',
								$user
						  );
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
						  // Return validation errors with 422 status codes
						  return responseJson(422, 'Validation failed', $e->errors());
					} catch (\Exception $e) {
						  // Handle other exceptions
						  return responseJson(500, 'Server error', $e->getMessage());
					}
			 }
			 
			 // Verify email with PIN
			 public function verifyEmail(Request $request)
			 {
					try {
						  $validated = $request->validate([
								'email'    => 'required|email',
								'pin_code' => 'required|digits:4'
						  ], [
								'email.required'    => 'Email is required',
								'email.email'       => 'Invalid email format',
								'pin_code.required' => 'PIN code is required',
								'pin_code.digits'   => 'PIN must be 4 digits'
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
								$user, $validated['pin_code'], 'verification'
						  )
						  ) {
								 // Mark email as verified for MustVerifyEmail
								 if (!$user->hasVerifiedEmail()) {
										$user->markEmailAsVerified();
								 }
								 // Create Passport token
//								 $token = $user->createToken('AuthToken')->accessToken;
								 return responseJson(
									  200, 'Email verified successfully', [
											'user' => $user
									  ]
								 );
						  }
						  
						  return responseJson(400, 'Invalid PIN code');
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
						  return responseJson(422, 'Validation error', [
								'errors' => $e->errors()
						  ]);
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error', [
								'error' => $e->getMessage()
						  ]);
					}
			 }
			 
			 // Regular login
			 public function login(Request $request)
			 {
					try {
						  $validated = $request->validate([
								'email'    => 'required|email',
								'password' => 'required'
						  ], [
								'email.required'    => 'The email field is required.',
								'email.email'       => 'Please enter a valid email address.',
								'password.required' => 'The password field is required.'
						  ]);
						  
						  // Attempt JWT authentication
						  if (!$token = JWTAuth::attempt($validated)) {
								 return responseJson(
									  401,
									  'Invalid credentials. Please check your email and password.'
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
						  
						  // Get token expiration time
						  $expiration = JWTAuth::factory()->getTTL() * 60;
						  
						  return responseJson(200, 'Login successful', [
								'access_token' => $token,
								'token_type'   => 'bearer',
								'expires_in'   => $expiration,
								'user'         => [
									 'id'    => $user->id,
									 'name'  => $user->name,
									 'email' => $user->email
								]
						  ]);
						  
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
						  
					} catch (\Exception $e) {
						  return responseJson(
								500, 'Login failed',
								config('app.debug') ? $e->getMessage()
									 : 'An error occurred'
						  );
					}
			 }
			 
			 // Social login redirect
			 public function redirectToProvider($provider)
			 {
					$validProviders = ['google', 'linkedin', 'github'];
					
					if (!in_array($provider, $validProviders)) {
						  return responseJson(400, 'Invalid provider');
					}
					
					$redirectUrl = url("/auth/{$provider}/callback");
					
					config(["services.{$provider}.redirect" => $redirectUrl]);
					
					$url = Socialite::driver($provider)
						 ->stateless()
						 ->redirectUrl($redirectUrl) // Explicitly set redirect URL
						 ->redirect()
						 ->getTargetUrl();
					
					return responseJson(200, 'Redirect URL generated', [
						 'redirect_url' => $url
					]);
			 }
			 
			 // Social login callback
			 public function handleProviderCallback($provider)
			 {
					try {
						  $socialUser = Socialite::driver($provider)->stateless()->user();
						  
						  // Find or create user
						  $user = User::firstOrCreate(
								['email' => $socialUser->getEmail()],
								[
									 'name' => $socialUser->getName(),
									 'provider_id' => $socialUser->getId(),
									 'provider_name' => $provider,
									 'password' => Hash::make(Str::random(32)),
									 'email_verified_at' => now() // Mark as verified
								]
						  );
						  
						  // Generate JWT token
						  $token = JWTAuth::fromUser($user, [
								'provider' => $provider,
								'avatar' => $socialUser->getAvatar()
						  ]);
						  
						  return responseJson(200, 'Login successful', [
								'access_token' => $token,
								'token_type' => 'bearer',
								'expires_in' => auth()->factory()->getTTL() * 60,
								'user' => [
									 'id' => $user->id,
									 'name' => $user->name,
									 'email' => $user->email,
									 'provider' => $provider
								]
						  ]);
						  
					} catch (\Exception $e) {
						  Log::error("Social auth failed: " . $e->getMessage());
						  return responseJson(401, 'Authentication failed. Please try again.');
					}
			 }
			 // Logout
			 public function logout()
			 {
					try {
						  auth()->logout();
						  return responseJson(200, 'Successfully logged out');
					} catch (\Exception $e) {
						  return responseJson(500, 'Failed to logout');
					}
			 }
			 
			 public function refresh()
			 {
					try {
						  // Manually check if token is blocklisted
						  if (auth()->payload()->get('jti') &&
								JWTAuth::getBlacklist()->has(auth()->payload())) {
								 return responseJson(401, 'User is logged out - please login again');
						  }
						  
						  $newToken = auth()->refresh();
						  
						  return responseJson(200, 'Token refreshed successfully', [
								'access_token' => $newToken,
								'token_type' => 'bearer',
								'expires_in' => auth()->factory()->getTTL() * 60
						  ]);
						  
					} catch (TokenExpiredException $e) {
						  return responseJson(401, 'Token has expired');
					} catch (TokenBlacklistedException $e) {
						  return responseJson(401, 'User is logged out - please login again');
					} catch (JWTException $e) {
						  return responseJson(401, 'Unauthenticated users');
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error during token refresh');
					}
			 }
			 
			 public function requestPasswordReset(Request $request)
			 {
					try {
						  $validated = $request->validate([
								'email' => 'required|email|exists:users,email'
						  ], [
								'email.required' => 'The email field is required.',
								'email.email' => 'Please enter a valid email address.',
								'email.exists' => 'No account found with this email address.'
						  ]);
						  
						  $user = User::where('email', $validated['email'])->first();
						  
						  // Delete existing pins
						  PasswordResetPin::where('email', $validated['email'])->delete();
						  
						  // Attempt to generate and send PIN
						  $pinResult = $this->pinService->generateAndSendPin($user, 'reset');
						  
						  if (!$pinResult['email_sent']) {
								 throw new \Exception('Failed to send password reset email');
						  }
						  
						  return responseJson(200, "Reset PIN sent to email");
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
						  return responseJson(422, 'Validation failed', [
								'errors' => $e->errors(),
								'message' => 'Please check your email address'
						  ]);
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Password reset request failed', [
								'error' => config('app.debug') ? $e->getMessage() : null,
								'message' => 'Please try again later'
						  ]);
					}
			 }
			 
			 public function verifyResetPin(Request $request)
			 {
					try {
						  $validated = $request->validate([
								'email' => 'required|email|exists:users,email',
								'pin'   => 'required|digits:4'
						  ]);
						  
						  $pinRecord = PasswordResetPin::where('email', $validated['email'])
								->where('pin', $validated['pin'])
								->where('created_at', '>', now()->subHours(1))
								->first();
						  
						  if (!$pinRecord) {
								 return responseJson(400, 'Invalid or expired PIN');
						  }
						  
						  $user = User::where('email', $validated['email'])->first();
						  
						  // Generate token with specific claims
						  $token = JWTAuth::customClaims([
								'purpose' => 'password_reset',
								'reset_id' => $pinRecord->id,
								'email' => $user->email
						  ])->fromUser($user);
						  
						  return responseJson(200, 'PIN verified successfully', [
								'reset_token' => $token,
								'token_type' => 'bearer',
								'expires_in' => config('jwt.reset_token_ttl', 1800) // 30 minutes
						  ]);
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'PIN verification failed');
					}
			 }
			 
			 public function resetPassword(Request $request)
			 {
					try {
						  $validated = $request->validate([
								'token' => 'required|string',
								'password' => 'required|string|min:8|confirmed'
						  ]);
						  
						  // Manually verify the token
						  try {
								 $payload = JWTAuth::setToken($validated['token'])->getPayload();
								 
								 if ($payload->get('purpose') !== 'password_reset') {
										return responseJson(401, 'Invalid token purpose');
								 }
								 
								 $user = User::where('email', $payload->get('email'))->first();
								 
								 if (!$user) {
										return responseJson(404, 'User not found');
								 }
								 
						  } catch (\Exception $e) {
								 return responseJson(401, 'Invalid reset token');
						  }
						  
						  // Update password
						  $user->password = Hash::make($validated['password']);
						  $user->save();
						  
						  // Invalidate the token
						  JWTAuth::setToken($validated['token'])->invalidate();
						  
						  return responseJson(200, 'Password reset successfully');
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Password reset failed');
					}
			 }
	  }