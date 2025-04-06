<?php
	  
	  namespace App\Http\Controllers\Auth;
	  
	  use App\Http\Controllers\Controller;
	  use App\Mail\PinNotificationMail;
	  use App\Models\PasswordResetPin;
	  use App\Models\User;
	  use App\Services\PinService;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Auth;
	  use Illuminate\Support\Facades\Hash;
	  use Illuminate\Support\Facades\Mail;
	  use Illuminate\Support\Str;
	  use Laravel\Socialite\Facades\Socialite;
	  
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
					$validated = $request->validate([
						 'name'     => 'required|string|max:255',
						 'email'    => 'required|string|email|unique:users',
						 'phone'    => 'required|string|unique:users',
						 'password' => 'required|string|min:8|confirmed'
					]);
					
					$user = User::create([
						 'name'            => $validated['name'],
						 'email'           => $validated['email'],
						 'phone'           => $validated['phone'],
						 'password'        => Hash::make($validated['password']),
						 'confirmed_email' => false
					]);
					
					// Generate and send PIN
//					$pin = $user->generateVerificationPin();
					$this->pinService->generateAndSendPin($user, 'verification');
					
					return responseJson(
						 201,
						 'Registration successful. you can login Now And Check email for PIN to verify Email.'
					);
			 }
			 
			 // Verify email with PIN
			 public function verifyEmail(Request $request)
			 {
					$request->validate([
						 'email'    => 'required|email',
						 'pin_code' => 'required|digits:4'
					]);
					
					$user = User::where('email', $request->email)->firstOrFail();
					
					if ($this->pinService->verifyPin(
						 $user, $request->pin, 'verification'
					)
					) {
						  $token = $user->createToken('AuthToken')->accessToken;
						  
						  return responseJson(
								200,
								'Email verified successfully',
								"$token"
						  );
						  
					}
					return responseJson(400, "Invalid PIN");
					
			 }
			 
			 // Regular login
			 public function login(Request $request)
			 {
					$credentials = $request->validate([
						 'email'    => 'required|email',
						 'password' => 'required'
					]);
					
					if (!Auth::attempt($credentials)) {
						  
						  return responseJson(401, 'Unauthorized');
					}
					
					$user = $request->user();
					$token = $user->createToken('AuthToken')->accessToken;
					
					return responseJson(
						 200, 'Login successful',
						 ['token' => $token,
						  'user'  => $user]
					);
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
					
					return responseJson( 200,"Reset PIN sent to email"
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