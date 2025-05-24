<?php
	  /*** In Admin Auth ***/
	  //			 public function checkResetPasswordPinCode(Request $request
	  //			 ): JsonResponse {
	  //					try {
	  //						  $validated = $request->validate([
	  //								'email'   => [
	  //									 'required',
	  //									 'string',
	  //									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
	  //									 'email',
	  //									 'exists:admins,email',
	  //									 'ascii' // Ensures only ASCII characters
	  //								],
	  //								'pinCode' => [
	  //									 'required',
	  //									 'digits:6',
	  //									 'numeric',
	  //									 'not_in:000000,111111,123456,654321',
	  //									 // Block common weak PINs
	  //								]
	  //						  ], [
	  //								'email.required'   => 'The email field is required.',
	  //								'email.regex'      => 'Invalid email format. Please use English characters only.',
	  //								'email.ascii'      => 'Email must contain only English characters.',
	  //								'pinCode.required' => 'PIN code is required',
	  //								'pinCode.digits'   => 'PIN must be exactly 6 digits',
	  //								'pinCode.numeric'  => 'PIN must contain only numbers',
	  //								'pinCode.not_in'   => 'This PIN is too common and insecure',
	  //						  ]);
	  //						  $admin = Admin::where('email', $validated['email'])->first(
	  //						  );
	  //						  // Verify PIN through PinService
	  //						  if (!$this->pinService->verifyPin(
	  //								$admin, $validated['pinCode'], 'reset'
	  //						  )
	  //						  ) {
	  //								 return responseJson(
	  //									  401, 'Unauthorized',
	  //									  'Expired PIN, Request a new PIN'
	  //								 );
	  //						  }
	  //						  $token = JWTAuth::claims([
	  //								'purpose' => 'password_reset',
	  //								'exp'     => now()->addMinutes(5)->timestamp
	  //						  ])->fromUser($admin);
	  //
	  //						  // Clear reset PIN record
	  //						  PasswordResetPin::where('email', $admin->email)
	  //								->where('type', 'admin')
	  //								->delete();
	  //
	  //						  return responseJson(
	  //								200, 'Check Successfully'
	  //								, ['token' => $token]
	  //						  );
	  //
	  //					} catch (\Illuminate\Validation\ValidationException $e) {
	  //						  return responseJson(
	  //								422,
	  //								" validation error",
	  //								$e->validator->errors()->all()
	  //						  );
	  //					} catch (\Exception $e) {
	  //						  $message = config('app.debug')
	  //								? "Check failed: " . $e->getMessage()
	  //								: "Check failed. Please try again later";
	  //
	  //						  return responseJson(500, 'Server Error', $message);
	  //					}
	  //			 }
	  //
	  //			 public function newPassword(Request $request): JsonResponse
	  //			 {
	  //					try {
	  //						  // Verify token claims
	  //						  $payload = auth('admin')->payload();
	  //
	  //						  if ($payload->get('purpose') !== 'password_reset') {
	  //								 return responseJson(
	  //									  401,
	  //									  'Unauthorized',
	  //									  "This token has expire"
	  //								 );
	  //						  }
	  //						  // Validate new password
	  //						  $validated = $request->validate([
	  //								'newPassword' => 'required|string|min:8|confirmed|regex:/^[a-zA-Z0-9@#$%^&*!]+$/'
	  //						  ], [
	  //
	  //								'newPassword.required'  => 'New password is required.',
	  //								'newPassword.confirmed' => 'Password confirmation does not match.',
	  //								'newPassword.min'       => 'Password must be at least 8 characters.',
	  //								'newPassword.regex'     => 'Password contains invalid characters. Use only English letters, numbers, and special symbols.',
	  //						  ]);
	  //						  // Update password
	  //						  $admin = auth('admin')->user();
	  //						  $admin->password = Hash::make($validated['newPassword']);
	  //						  $admin->save();
	  //						  // Invalidate token
	  //						  auth('admin')->invalidate(true);
	  //						  auth('admin')->logout();
	  //						  // Optional: Add token invalidation if using JWT
	  //						  JWTAuth::invalidate(JWTAuth::getToken());
	  //						  // Optional: Clear session data
	  //						  session()->flush();
	  //						  return responseJson(
	  //								200, 'Your Password updated successfully'
	  //						  );
	  //					} catch (\Illuminate\Validation\ValidationException $e) {
	  //						  return responseJson(
	  //								422,
	  //								" validation error",
	  //								$e->validator->errors()->all()
	  //						  );
	  //					} catch (TokenExpiredException $e) {
	  //						  return responseJson(
	  //								401, 'Unauthorized',
	  //								"Token expired Your session has expired"
	  //						  );
	  //
	  //					} catch (\Exception $e) {
	  //						  Log::error('Password Reset Error: ' . $e->getMessage());
	  //						  $message = config('app.debug')
	  //								? "Server error: " . $e->getMessage()
	  //								: "An unexpected error occurred. Please try again later.";
	  //						  return responseJson(500, 'Server Error', $message);
	  //					}
	  //			 }
	  //	  }

/*** ***/