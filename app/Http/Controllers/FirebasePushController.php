<?php
	  
	  namespace App\Http\Controllers;
	  
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Auth;
	  
	  class FirebasePushController extends Controller
	  {
			 public function registerToken(Request $request): JsonResponse
			 {
					$request->validate([
						 'fcm_token' => 'required|string',
					]);
					
					$user = Auth::user();
					$user->fcm_token = $request->fcm_token;
					$user->save();
					
					return response()->json([
						 'status' => 200,
						 'message' => 'FCM token registered successfully',
					]);
			 }
	  }