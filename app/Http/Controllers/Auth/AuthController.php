<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerificationPinMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
//use Laravel\Socialite\Facades\Socialite;
//use App\Mail\VerificationPinMail;

class AuthController extends Controller
{
	  // Regular registration
	  public function register(Request $request): \Illuminate\Http\JsonResponse
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
			 
			 return responseJson(201,"done");
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
					return response()->json(['error' => 'Invalid PIN code'], 400);
			 }
			 
			 return response()->json(['message' => 'Email verified successfully']);
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
}
