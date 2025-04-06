<?php
	  
	  namespace App\Http\Controllers\Auth;
	  
	  use App\Http\Controllers\Controller;
	  use App\Mail\VerificationPinMail;
	  use App\Models\User;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Auth;
	  use Illuminate\Support\Facades\Hash;
	  use Illuminate\Support\Facades\Mail;
	  use Laravel\Socialite\Facades\Socialite;
	  
	  class AuthController extends Controller
	  {
			 // Regular registration
			 public function register(Request $request)
			 {
					$validated = $request->validate([
						 'name' => 'required|string|max:255',
						 'email' => 'required|string|email|unique:users',
						 'phone' => 'required|string|unique:users',
						 'password' => 'required|string|min:8|confirmed'
					]);
					
					$user = User::create([
						 'name' => $validated['name'],
						 'email' => $validated['email'],
						 'phone' => $validated['phone'],
						 'password' => Hash::make($validated['password']),
						 'confirmed_email' => false
					]);
					
					// Generate and send PIN
					$pin = $user->generateVerificationPin();
					Mail::to($user->email)->send(new VerificationPinMail($pin));
					
					return response()->json(['message' => 'Registration successful. Check email for PIN.'], 201);
			 }
			 
			 // Verify email with PIN
			 public function verifyEmail(Request $request)
			 {
					$request->validate([
						 'email' => 'required|email',
						 'pin_code' => 'required|digits:4'
					]);
					
					$user = User::where('email', $request->email)->firstOrFail();
					
					if (!$user->verifyPin($request->pin_code)) {
						  return response()->json(['error' => 'Invalid PIN'], 400);
					}
					
					$token = $user->createToken('AuthToken')->accessToken;
					
					return response()->json([
						 'message' => 'Email verified successfully',
						 'access_token' => $token
					]);
			 }
			 
			 // Regular login
			 public function login(Request $request)
			 {
					$credentials = $request->validate([
						 'email' => 'required|email',
						 'password' => 'required'
					]);
					
					if (!Auth::attempt($credentials)) {
						  return response()->json(['error' => 'Unauthorized'], 401);
					}
					
					$user = $request->user();
					$token = $user->createToken('AuthToken')->accessToken;
					
					return response()->json([
						 'user' => $user,
						 'access_token' => $token
					]);
			 }
			 
			 // Social login redirect
			 public function redirectToProvider($provider)
			 {
					$validProviders = ['google', 'linkedin', 'github'];
					
					if (!in_array($provider, $validProviders)) {
						  return response()->json(['error' => 'Invalid provider'], 400);
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
						  $socialUser = Socialite::driver($provider)->stateless()->user();
						  
						  $user = User::firstOrCreate(
								['email' => $socialUser->getEmail()],
								[
									 'name' => $socialUser->getName(),
									 'provider_id' => $socialUser->getId(),
									 'provider_name' => $provider,
									 'confirmed_email' => true,
									 'password' => Hash::make(rand().time())
								]
						  );
						  
						  $token = $user->createToken('SocialToken')->accessToken;
						  
						  return response()->json([
								'user' => $user,
								'access_token' => $token
						  ]);
						  
					} catch (\Exception $e) {
						  return response()->json(['error' => 'Social authentication failed'], 401);
					}
			 }
			 
			 // Logout
			 public function logout(Request $request)
			 {
					$request->user()->token()->revoke();
					return response()->json(['message' => 'Successfully logged out']);
			 }
	  }