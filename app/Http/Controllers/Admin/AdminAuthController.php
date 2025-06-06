<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Mail\SubAdminCredentialsMail;
	  use App\Models\Admin;
	  use App\Models\PasswordResetPin;
	  use App\Notifications\AdminApprovedNotification;
	  use App\Services\PinService;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Hash;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Mail;
	  use Illuminate\Support\Facades\Storage;
	  use Illuminate\Support\Str;
	  use Illuminate\Validation\ValidationException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
	  use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
	  use Spatie\Permission\Exceptions\PermissionAlreadyExists;
	  use Spatie\Permission\Exceptions\RoleAlreadyExists;
	  
	  class AdminAuthController extends Controller
	  {
			 protected PinService $pinService;
			 
			 public function __construct(PinService $pinService)
			 {
					$this->pinService = $pinService;
			 }
			 public function superAdminSignUp(Request $request): JsonResponse
			 {
					try {
						  // Validate the request data
						  $validated = $request->validate([
								'fullName' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
								'email'    => [
									 'required',
									 'string',
									 'email','emal:filter',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
									 'unique:admins,email',
									 'unique:users,email',
									 'ascii' // Ensures only ASCII characters
								],
								'photo'    => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
								'phone'    => 'required|string|max_digits:11|unique:admins,phone',
								'password' => 'required|string|min:8|confirmed|regex:/^[a-zA-Z0-9@#$%^&*!]+$/',
						  ], [
								 // Custom error messages
								 'fullName.required' => 'The name field is required.',
								 'fullName.regex'    => 'Name must contain only English letters and spaces.',
								 
								 'email.required' => 'The email field is required.',
								 'email.regex'    => 'Invalid email format. Please use English characters only.',
								 'email.ascii'    => 'Email must contain only English characters.',
								 
								 'photo.image' => 'The photo must be an image.',
								 'photo.mimes' => 'The photo must be a file of type: jpeg, png, jpg, gif, svg.',
								 'photo.max'   => 'The photo cannot exceed 2MB in size.',
								 
								 'password.required'  => 'The password field is required.',
								 'password.confirmed' => 'Password confirmation does not match.',
								 'password.min'       => 'Password must be at least 8 characters.',
								 'password.regex'     => 'Password contains invalid characters. Use only English letters, numbers, and special symbols.',
						  ]);
						  
						  // Handle logo upload
						  if ($request->hasFile('photo')) {
								 $photoPath = $request->file('photo')->store(
									  'admin_images', 'public'
								 );
								 $validated['photo'] = $photoPath;
						  } else {
								 // Set default image URL
								 $validated['photo']
									  = 'https://jobizaa.com/still_images/userDefault.jpg';
						  }
						  
						  // Create admin if validation passes
						  $admin = Admin::create([
								'name'        => $validated['fullName'],
								'email'       => $validated['email'],
								'phone'       => $validated['phone'],
								'photo'       => $validated['photo'],
								'password'    => Hash::make($validated['password']),
								'is_approved' => true,
								'company_id'  => null,
						  ]);
						  
						  $pinResult = $this->pinService->generateAndSendPin(
								$admin, 'verification'
						  );
						  
						  if (!$pinResult['email_sent']) {
								 $admin->delete();
								 return responseJson(
									  500, 'Registration failed ',
									  'Registration failed - email not sent'
								 );
						  }
						  $admin->update([
								'approved_by' => $admin->id,
						  ]);
						  // Assign a super-admin role and basic permissions
						  $admin->assignRole('super-admin');
						  
						  return responseJson(
								201,
								'Verify email to activate. Admin created. Your account As super-admin '
								, [
									 'id'       => $admin->id,
									 'fullName' => $admin->name,
									 'email'    => $admin->email,
								]
						  );
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
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
								 $errorMessage = "Server error: " . $e->getMessage();
						  }
						  return responseJson(500, 'Server error', $errorMessage);
					}
			 }
			 
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
									 'unique:admins,email',
									 'unique:users,email',
									 'ascii' // Ensures only ASCII characters
								],
								'photo'    => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
								'phone'    => 'required|string|max_digits:11|unique:admins,phone',
								'password' => 'required|string|min:8|confirmed|regex:/^[a-zA-Z0-9@#$%^&*!]+$/'
						  ], [
								 // Custom error messages
								 'fullName.required' => 'The name field is required.',
								 'fullName.regex'    => 'Name must contain only English letters and spaces.',
								 
								 'email.required' => 'The email field is required.',
								 'email.regex'    => 'Invalid email format. Please use English characters only.',
								 'email.ascii'    => 'Email must contain only English characters.',
								 
								 'photo.image' => 'The photo must be an image.',
								 'photo.mimes' => 'The photo must be a file of type: jpeg, png, jpg, gif, svg.',
								 'photo.max'   => 'The photo cannot exceed 2MB in size.',
								 
								 'password.required'  => 'The password field is required.',
								 'password.confirmed' => 'Password confirmation does not match.',
								 'password.min'       => 'Password must be at least 8 characters.',
								 'password.regex'     => 'Password contains invalid characters. Use only English letters, numbers, and special symbols.',
						  ]);
						  
						  // Handle logo upload
						  if ($request->hasFile('photo')) {
								 $photoPath = $request->file('photo')->store(
									  'admin_images', 'public'
								 );
								 $urlPath = Storage::disk('public')->url($photoPath);
								 $validated['photo'] = $urlPath;
						  } else {
								 // Set default image URL
								 $validated['photo']
									  = 'https://jobizaa.com/still_images/userDefault.jpg';
						  }
						  
						  // Create admin if validation passes
						  $admin = Admin::create([
								'name'        => $validated['fullName'],
								'email'       => $validated['email'],
								'phone'       => $validated['phone'],
								'photo'       => $validated['photo'],
								'password'    => Hash::make($validated['password']),
								'is_approved' => true, // Default unapproved
								'company_id'  => null,
						  ]);
						  
						  $pinResult = $this->pinService->generateAndSendPin(
								$admin, 'verification'
						  );
						  if (!$pinResult['email_sent']) {
								 $admin->delete();
								 return responseJson(
									  500, 'Registration failed ', 'email not sent'
								 );
						  }
						  $admin->update([
								'approved_by' => $admin->id,
						  ]);
						  $admin->assignRole('admin');
//						   Assign a pending role and basic permissions
//						  $admin->givePermissionTo('access-pending');
//						  "And  Your account requires super-admin approval"
//						   Notify all super-admins
//						  $superAdmins = Admin::role('super-admin')->get();
//						  Notification::send(
//								$superAdmins, new AdminRegistrationPending($admin)
//						  );
						  return responseJson(
								201,
								'Admin created. Verify email to activate'
								, [
									 'id'       => $admin->id,
									 'fullName' => $admin->name,
									 'email'    => $admin->email,
								]
						  );
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
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
								 $errorMessage = "Server error: " . $e->getMessage();
						  }
						  return responseJson(500, 'Server error', $errorMessage);
					}
			 }
			 
			 public function verifyEmail(Request $request): JsonResponse
			 {
					try {
						  $validated = $request->validate([
								'email'    => [
									 'required',
									 'string',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
									 'exists:admins,email',
									 'ascii' // Ensures only ASCII characters
								],
								'pin_code' => [
									 'required',
									 'max_digits::6',
									 'string',
									 'not_in:0000,1111,1234,4321',
									 // Block common weak PINs
								]
						  ], [
								'email.required'   => 'The email field is required.',
								'email.regex'      => 'Invalid email format. Please use English characters only.',
								'email.ascii'      => 'Email must contain only English characters.',
								'pinCode.required' => 'PIN code is required',
								'pinCode.digits'   => 'PIN must be exactly 6 digits',
								'pinCode.string'   => 'PIN must contain only numbers',
								'pinCode.not_in'   => 'This PIN is too common and insecure',
						  ]);
						  
						  // Find user without failing immediately
						  $admin = Admin::where('email', $request->email)->first();
						  
						  if (!$admin) {
								 return responseJson(
									  404,
									  'Admin not found',
									  'Please check your email or register first'
								 );
						  }
						  
						  // Verify PIN using your service
						  if ($this->pinService->verifyPin(
								$admin, $validated['pin_code'], 'verification'
						  )
						  ) {
								 // Mark email as verified for MustVerifyEmail
								 if (!$admin->hasVerifiedEmail()) {
										$admin->markEmailAsVerified();
								 }
								 return responseJson(
									  200, 'Email verified successfully'
								 );
						  }
						  
						  return responseJson(400, 'Error', 'Invalid PIN code');
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
						  return responseJson(
								422,
								" validation error",
								$e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  return responseJson(
								500, 'Server error',
								$e->getMessage()
						  );
					}
			 }
			 
			 public function approve($pendingAdmin): JsonResponse
			 {
					try {
						  if (!auth('admin')->user()->hasRole('super-admin')) {
								 return responseJson(
									  403, 'Unauthorized',
									  'Only super-admins can approve'
								 );
						  }
						  $admin = Admin::find($pendingAdmin);
						  if (!$admin) {
								 return responseJson(404, 'Error', 'Admin not found');
						  }
						  if ($admin->is_approved) {
								 return responseJson(
									  403,
									  'Error',
									  'This account is already approved '
								 );
						  }
						  
						  DB::transaction(function () use ($admin) {
								 // Remove pending status
								 $admin->update([
									  'is_approved' => true,
									  'approved_by' => auth()->id(),
								 ]);
								 
								 // Remove temporary role/permissions
								 $admin->removeRole('pending');
								 $admin->revokePermissionTo('access-pending');
								 
								 // Assign a default admin role with permissions
								 $admin->assignRole('admin');
								 $admin->givePermissionTo(
									  ['manage-own-company', 'manage-company-jobs',
										'manage-company-admins']
								 );
								 
								 // Send approval notification
								 $admin->notify(new AdminApprovedNotification());
						  });
						  
						  return responseJson(200, 'Admin approved successfully', [
								'admin_id'   => $admin->id,
								'admin_name' => $admin->name,
								'new_role'   => 'admin'
						  ]);
						  
					} catch (RoleAlreadyExists $e) {
						  return responseJson(500, 'Error', 'Role already exists.');
					} catch (PermissionAlreadyExists $e) {
						  return responseJson(
								500, 'Error', 'Permission already exists.'
						  );
					} catch (\Exception $e) {
						  return responseJson(
								500, 'Server error', $e->getMessage()
						  );
					}
					
			 }
			 
			 public function createSubAdmin(Request $request): JsonResponse
			 {
					try {
						  // Validate input
						  $validated = $request->validate([
								'fullName' => [
									 'required',
									 'string',
									 'max:255',
									 'regex:/^[a-zA-Z\s]+$/', // Only letters and spaces
								],
								'email'    => [
									 'required',
									 'string',
									 'email',
									 'max:255',
									 'unique:admins,email',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
								],
								'phone'    => 'required|string|max_digits:11|unique:admins,phone',
								'role'     => 'required|in:hr,coo',
						  ], [
								'fullName.regex' => 'Name must only contain letters and spaces',
						  ]);
						  
						  $admin = auth('admin')->user();
						  
						  // Check if an authenticated user is valid and has permission
						  if (!$admin instanceof Admin
								|| !$admin->hasPermissionTo(
									 'manage-company-admins'
								)
						  ) {
								 return responseJson(
									  403, 'Unauthorized', 'Not Allow For Add Sub Admin'
								 );
						  }
						  
						  // Check if admin has a company
						  if (empty($admin->company_id)) {
								 return responseJson(
									  403,
									  'Forbidden',
									  'You must create a company before adding sub-admins'
								 );
						  }
						  
						  // Limit sub-admins to 8
						  $subAdminCount = Admin::where(
								'company_id', $admin->company_id
						  )
								->where('id', '!=', $admin->id)
								->count();
						  
						  if ($subAdminCount >= 8) {
								 return responseJson(
									  403, 'Forbidden',
									  'You can only create up to 8 sub-admins'
								 );
						  }
						  
						  // Generate random password
						  $password = Str::random(12);
						  
						  // Create sub-admin
						  $subAdmin = Admin::create([
								'name'              => $validated['fullName'],
								'email'             => $validated['email'],
								'phone'             => $validated['phone'],
								'password'          => Hash::make($password),
								'company_id'        => $admin->company_id,
								'is_approved'       => true,
								'approved_by'       => $admin->id,
								'confirmed_email'   => true,
								'email_verified_at' => now(), // Auto-verify email
						  ]);
						  // Mark email as verified for MustVerifyEmail
						  if (!$subAdmin->hasVerifiedEmail()) {
								 $subAdmin->markEmailAsVerified();
						  }
						  // Assign role
						  $subAdmin->assignRole($validated['role']);
						  
						  // Send credentials email
						  Mail::to($validated['email'])->send(
								new SubAdminCredentialsMail(
									 name: $validated['fullName'],
									 email: $validated['email'],
									 password: $password,
									 role: $validated['role']
								)
						  );
						  
						  return responseJson(
								201, 'Sub-admin created and email sent', [
									 'sub_admin_id'    => $subAdmin->id,
									 'sub_admin_name'  => $subAdmin->name,
									 'sub_admin_email' => $subAdmin->email,
									 'sub_admin_role'  => $validated['role'],
								]
						  );
						  
					} catch (ValidationException $e) {
						  return responseJson(
								422,
								'Validation error',
								$e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Server error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error', $e->getMessage()
						  );
					}
			 }
			 
			 public function login(Request $request): \Illuminate\Http\JsonResponse
			 {
					try {
						  $validated = $request->validate([
								'email'    => [
									 'required',
									 'string',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
									 'email',
									 'exists:admins,email', //
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
						  if (!$token = auth('admin')->attempt($validated)) {
								 return responseJson(
									  401, 'Unauthorized', 'Email Or Password Invalid'
								 );
						  }
						  $admin = auth('admin')->user();
						  
						  // Email verification check
						  if (!$admin->hasVerifiedEmail()) {
								 auth('admin')->logout();
								 return responseJson(
									  403, 'Forbidden',
									  'Please verify your email address before logging in'
								 );
						  }
						  // SuperAdmin check approved
						  if (!$admin->is_approved) {
								 auth('admin')->logout();
								 return responseJson(
									  403, 'Forbidden',
									  'Account pending approval, Please contact the administrator'
								 );
						  }
						  return responseJson(
								200, 'Login successful',
								[
									 'token'       => $token,
									 'id'          => $admin->id,
									 'fullName'    => $admin->name,
									 'email'       => $admin->email,
									 'roles'       => $admin->getRoleNames(),
									 'permissions' => $admin->getAllPermissions()->pluck(
										  'name'
									 )]
						  );
					} catch (\Illuminate\Validation\ValidationException $e) {
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
								 $errorMessage = "Server error: " . $e->getMessage();
						  }
						  return responseJson(500, 'Server Error', $errorMessage);
					}
			 }
			 
			 public function logout(): \Illuminate\Http\JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  
						  if (!$admin) {
								 return responseJson(
									  401, 'Unauthorized', 'No authenticated user found'
								 );
						  }
						  
						  auth()->logout();
						  
						  // Optional: Add token invalidation if using JWT
						  JWTAuth::invalidate(JWTAuth::getToken());
						  
						  // Optional: Clear session data
						  session()->flush();
						  
						  return responseJson(200, 'Successfully logged out');
						  
					} catch (TokenInvalidException $e) {
						  return responseJson(
								401, 'Unauthorized', 'Invalid authentication token'
						  );
						  
					} catch (JWTException $e) {
						  return responseJson(
								500, 'Server Error', 'Could not invalidate token'
						  );
						  
					} catch (\Exception $e) {
						  Log::error('Logout Error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server Error', 'Logout failed due to server error'
						  );
					}
					
			 }
//			 public function forgotAdminPassword(Request $request
//			 ): \Illuminate\Http\JsonResponse {
//					try {
//						  $validated = $request->validate([
//								'email' => 'required|email|exists:admins,email'
//						  ], [
//								'email.required' => 'The email field is required.',
//								'email.email'    => 'Please enter a valid email address.',
//								'email.exists'   => 'No account found with this email address.'
//						  ]);
//						  $admin = Admin::where('email', $request->email)->first();
//
//						  // Delete existing pins
//						  PasswordResetPin::where('email', $validated['email'])
//								->delete();
//
//						  // Attempt to generate and send PIN
//						  $pinResult = $this->pinService->generateAndSendPin(
//								$admin, 'reset'
//						  );
//
//						  if (!$pinResult['email_sent']) {
//								 throw new \Exception(
//									  'Failed to send password reset email'
//								 );
//						  }
//
//						  return responseJson(200, "Reset PIN sent to email");
//
//					} catch (\Illuminate\Validation\ValidationException $e) {
//						  return responseJson(422, 'Validation failed', [
//								'errors'  => $e->errors(),
//								'message' => 'Please check your email address'
//						  ]);
//
//					} catch (\Exception $e) {
//						  return responseJson(500, 'Password reset request failed', [
//								'error'   => config('app.debug') ? $e->getMessage()
//									 : null,
//								'message' => 'Please try again later'
//						  ]);
//					}
//
//			 }
//
			 public function requestPasswordReset(Request $request): JsonResponse
			 {
					try {
						  $validated = $request->validate([
								'email' => [
									 'required',
									 'string',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
									 'email',
									 'exists:admins,email',
									 'ascii' // Ensures only ASCII characters
								]
						  ], [
								'email.required' => 'The email field is required.',
								'email.email'    => 'Please enter a valid email address.',
								'email.exists'   => 'This Account Not found in App.',
								'email.regex'    => 'Invalid email format. Please use English characters only.',
								'email.ascii'    => 'Email must contain only English characters.',
						  ]);
						  
						  $admin = Admin::where('email', $validated['email'])->first(
						  );
						  
						  // Delete existing pins
						  PasswordResetPin::where('email', $validated['email'])
								->delete();
						  
						  // Attempt to generate and send PIN
						  $pinResult = $this->pinService->generateAndSendPin(
								$admin, 'reset'
						  );
						  
						  if (!$pinResult['email_sent']) {
								 throw new \Exception(
									  'Failed to send password reset email'
								 );
						  }
						  
						  return responseJson(
								200, "Reset PIN sent successfully to email"
						  );
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
						  return responseJson(
								422,
								" validation error",
								$e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  $errorMessage
								= 'Password reset request failed. Please try again later.';
						  
						  if (config('app.debug')) {
								 $errorMessage .= " " . $e->getMessage();
						  }
						  
						  Log::error('Password Reset Error: ' . $e->getMessage());
						  return responseJson(500, 'Server Error', $errorMessage);
					}
			 }
			 
			 public function newPassword(Request $request
			 ): JsonResponse {
					try {
						  $validated = $request->validate([
								'email'       =>
									 ['required',
									  'string',
									  'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
									  'email',
									  'exists:admins,email',
									  'ascii'],// Ensures only ASCII characters,
								'pinCode'     =>
									 ['required',
									  'max_digits::6',
									  'string',
									  'not_in:0000,1111,1234,4321',],
								'newPassword' => 'required|string|min:8|confirmed|regex:/^[a-zA-Z0-9@#$%^&*!]+$/'
						  ], [
								
								'email.required'        => 'The email field is required.',
								'email.email'           => 'Please enter a valid email address.',
								'email.exists'          => 'This Account Not found in App.',
								'email.regex'           => 'Invalid email format. Please use English characters only.',
								'email.ascii'           => 'Email must contain only English characters.',
								'pinCode.required'      => 'PIN code is required',
								'pinCode.digits'        => 'PIN must be exactly 6 digits',
								'pinCode.numeric'       => 'PIN must contain only numbers',
								'pinCode.not_in'        => 'This PIN is too common and insecure',
								'newPassword.required'  => 'New password is required.',
								'newPassword.confirmed' => 'Password confirmation does not match.',
								'newPassword.min'       => 'Password must be at least 8 characters.',
								'newPassword.regex'     => 'Password contains invalid characters. Use only English letters, numbers, and special symbols.',
						  ]);
						  
						  $admin = Admin::where('email', $validated['email'])->first(
						  );
						  
						  // Verify PIN through PinService
						  if (!$this->pinService->verifyPin(
								$admin, $validated['pinCode'], 'reset'
						  )
						  ) {
								 return responseJson(
									  401, 'Unauthorized',
									  'Expired PIN, Request a new PIN'
								 );
						  }
						  
						  // Update password
						  $admin->update([
								'password' => Hash::make($validated['newPassword'])
						  ]);
						  
						  // Clear reset PIN record
						  PasswordResetPin::where('email', $admin->email)
								->where('type', 'admin')
								->delete();
						  
						  return responseJson(200, 'Password reset successfully', [
								'email' => $admin->email
						  ]);
						  
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation failed', $e->errors());
					} catch (\Exception $e) {
						  return responseJson(500, 'Password reset failed', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
	  }
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