<?php
	  
	  namespace App\Http\Controllers\Auth;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\PasswordResetPin;
	  use App\Models\User;
	  use App\Services\PinService;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Auth;
	  use Illuminate\Support\Facades\Hash;
	  use Illuminate\Support\Str;
	  use Illuminate\Validation\ValidationException;
	  use Laravel\Socialite\Facades\Socialite;

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
						  
						  if (!Auth::attempt($validated)) {
								 return responseJson(
									  401,
									  'Invalid credentials, Please check your email and password '
								 );
						  }
						  
						  $user = Auth::user();
						  
						  // Check if email is verified (if using MustVerifyEmail)
						  if ($user instanceof
								\Illuminate\Contracts\Auth\MustVerifyEmail
								&& !$user->hasVerifiedEmail()
						  ) {
								 return responseJson(
									  403,
									  'Please verify your email address before logging in'
								 );
						  }
						  
						  $token = $user->createToken('authToken')->accessToken;
						  
						  return responseJson(200,
								'Login successful', [
									 'token' => $token,
									 'user'  => [
										  'id'    => $user->id,
										  'name'  => $user->name,
										  'email' => $user->email
									 ]
								]
						  );
						  
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error',
								 $e->errors());
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Login failed',
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
						  return responseJson(
								400, 'Invalid provider'
						  );
					}
					
					$url = Socialite::driver($provider)
						 ->stateless()
						 ->redirect()
						 ->getTargetUrl();
					
					return response()->json(['redirect_url' => $url]);
			 }
			 
			 // Social login callback
			 public function handleProviderCallback($provider)
			 {
					try {
						  $socialUser = Socialite::driver($provider)->stateless()
								->user();
						  
						  $user = User::firstOrCreate(
								['email' => $socialUser->getEmail()],
								[
									 'name'            => $socialUser->getName(),
									 'provider_id'     => $socialUser->getId(),
									 'provider_name'   => $provider,
									 'confirmed_email' => true,
									 'password'        => Hash::make(rand() . time())
								]
						  );
						  
						  $token = $user->createToken('SocialToken')->accessToken;
						  
						  return responseJson(
								200, "Login successful",
								['token' => $token, 'user' => $user,]
						  );
						  
					} catch (\Exception $e) {
						  return responseJson(401, 'Social authentication failed');
					}
			 }
			 
			 // Logout
			 public function logout(Request $request)
			 {
					$request->user()->token()->revoke();
					return responseJson(
						 200, 'Successfully logged out'
					);
			 }
			 
			 public function requestPasswordReset(Request $request)
			 {
					$request->validate(
						 ['email' => 'required|email|exists:users,email']
					);
					
					$email = $request->email;
//					$pin = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
					$user = User::where('email', $request->email)->first();
					// Delete any existing pins for this email
					PasswordResetPin::where('email', $email)->delete();
					
					$this->pinService->generateAndSendPin($user, 'reset');
					
					return responseJson(
						 200, "Reset PIN sent to email"
					);
			 }
			 
			 public function verifyResetPin(Request $request)
			 {
					$request->validate([
						 'email' => 'required|email',
						 'pin'   => 'required|digits:4'
					]);
					
					$pinRecord = PasswordResetPin::where('email', $request->email)
						 ->where('pin', $request->pin)
						 ->where('created_at', '>', now()->subHours(1))
						 ->first();
					
					if (!$pinRecord) {
						  return response()->json(
								['error' => 'Invalid or expired PIN'], 400
						  );
					}
					
					// Generate a one-time token for password reset
					$token = Str::random(60);
					$pinRecord->update(['token' => $token]);
					
					return response()->json(['reset_token' => $token]);
			 }
			 
			 public function resetPassword(Request $request)
			 {
					$request->validate([
						 'email'    => 'required|email',
						 'token'    => 'required|string',
						 'password' => 'required|string|min:8|confirmed'
					]);
					
					$pinRecord = PasswordResetPin::where('email', $request->email)
						 ->where('token', $request->token)
						 ->where('created_at', '>', now()->subHours(1))
						 ->first();
					
					if (!$pinRecord) {
						  return response()->json(
								['error' => 'Invalid or expired token'], 400
						  );
					}
					
					// Update user password
					$user = User::where('email', $request->email)->firstOrFail();
					$user->password = Hash::make($request->password);
					$user->save();
					
					// Delete used pin record
					$pinRecord->delete();
					
					return response()->json(
						 ['message' => 'Password reset successfully']
					);
			 }
	  }