<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Admin;
	  use App\Models\PasswordResetPin;
	  use App\Notifications\AdminApprovedNotification;
	  use App\Notifications\AdminRegistrationPending;
	  use App\Services\PinService;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Hash;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Notification;
	  use Illuminate\Validation\ValidationException;
	  use Spatie\Permission\Exceptions\PermissionAlreadyExists;
	  use Spatie\Permission\Exceptions\RoleAlreadyExists;
	  
	  class AdminAuthController extends Controller
	  {
			 protected $pinService;
			 
			 public function __construct(PinService $pinService)
			 {
					$this->pinService = $pinService;
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
								'phone'    => 'required|string|max_digits:11|unique:admins,phone',
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
						  
						  // Create admin if validation passes
						  $admin = Admin::create([
								'name'        => $validated['fullName'],
								'email'       => $validated['email'],
								'phone'       => $validated['phone'],
								'password'    => Hash::make($validated['password']),
								'is_approved' => false, // Default unapproved
								'company_id'  => null,
						  ]);
						  
						  $pinResult = $this->pinService->generateAndSendPin(
								$admin, 'verification'
						  );
						  
						  if (!$pinResult['email_sent']) {
								 $admin->delete();
								 return responseJson(
									  500, 'Registration failed - email not sent'
								 );
						  }
						  
						  // Assign a pending role and basic permissions
						  $admin->assignRole('pending');
						  $admin->givePermissionTo('access-pending');
						  
						  // Notify all super-admins
						  $superAdmins = Admin::role('super-admin')->get();
						  Notification::send(
								$superAdmins, new AdminRegistrationPending($admin)
						  );
						  
						  return responseJson(
								201,
								'Admin created. Verify email to activate. And  Your account requires super-admin approval'
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
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 public function verifyEmail(Request $request): JsonResponse
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
						  $admin = Admin::where('email', $request->email)->first();
						  
						  if (!$admin) {
								 return responseJson(
									  404,
									  'Admin not found. Please check your email or register first.'
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
			 
			 public function approve(Admin $pendingAdmin): JsonResponse
			 {
					try {
						  if (!auth()->user()->hasRole('super-admin')) {
								 return responseJson(
									  403, 'Unauthorized: Only super-admins can approve'
								 );
						  }
						  
						  if ($pendingAdmin->is_approved) {
								 return responseJson(
									  403,
									  'This account is already approved '
								 );
						  }
						  
						  DB::transaction(function () use ($pendingAdmin) {
								 // Remove pending status
								 $pendingAdmin->update([
									  'is_approved' => true,
									  'approved_by' => auth()->id(),
								 ]);
								 
								 // Remove temporary role/permissions
								 $pendingAdmin->removeRole('pending');
								 $pendingAdmin->revokePermissionTo('access-pending');
								 
								 // Assign a default admin role with permissions
								 $pendingAdmin->assignRole('admin');
								 $pendingAdmin->givePermissionTo(
									  ['manage-own-company', 'manage-company-jobs',
										'manage-company-admins']
								 );
								 
								 // Send approval notification
								 $pendingAdmin->notify(new AdminApprovedNotification());
						  });
						  
						  return responseJson(200, 'Admin approved successfully', [
								'admin_id'   => $pendingAdmin->id,
								'admin_name' => $pendingAdmin->name,
								'new_role'   => 'admin'
						  ]);
						  
					} catch (RoleAlreadyExists $e) {
						  return responseJson(500, 'Error: Role already exists.');
					} catch (PermissionAlreadyExists $e) {
						  return responseJson(
								500, 'Error: Permission already exists.'
						  );
					} catch (\Exception $e) {
						  return responseJson(
								500, 'Server error: ' . $e->getMessage()
						  );
					}
					
			 }
			 
			 // Update approve method
			 
			 public function createSubAdmin(Request $request): JsonResponse
			 {
					$request->validate([
						 'fullName' => 'required|string|max:255',
						 'email'    => 'required|email|unique:admins',
						 'password' => 'required|min:8',
						 'role'     => 'required|in:hr,coo'
					]);
					
					$admin = auth('admin')->user();
					
					if (!$admin instanceof \App\Models\Admin
						 || !$admin->hasPermissionTo('manage-company-admins')
					) {
						  return responseJson(403, 'Unauthorized');
					}
					
					$subAdmin = Admin::create([
						 'name'            => $request->fullName,
						 'email'           => $request->email,
						 'password'        => bcrypt($request->password),
						 'company_id'      => $admin->company_id,
						 'is_approved'     => true,
						 'approved_by'     => $admin->id,
						 'confirmed_email' => true,
					
					]);
					// Mark email as verified for MustVerifyEmail
					if (!$admin->hasVerifiedEmail()) {
						  $admin->markEmailAsVerified();
					}
					
					$subAdmin->assignRole($request->role);
					
					return responseJson(201, 'Sub-admin created', [
						 'SubAdmin_id'    => $subAdmin->id,
						 'SubAdmin_name'  => $subAdmin->name,
						 'SubAdmin_email' => $subAdmin->email,
					]);
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
								 return responseJson(401, 'Email Or Password Invalid');
						  }
						  $admin = auth('admin')->user();
						  
						  // Email verification check
						  if (!$admin->hasVerifiedEmail()) {
								 auth('admin')->logout();
								 return responseJson(
									  403,
									  'Please verify your email address before logging in'
								 );
						  }
						  // SuperAdmin check approved
						  if (!$admin->is_approved) {
								 auth('admin')->logout();
								 return responseJson(
									  403,
									  'Account pending approval, Please contact the administrator'
								 );
						  }
//						  return $this->respondWithToken($token, $admin);
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
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 public function logout(): \Illuminate\Http\JsonResponse
			 {
					auth('admin')->logout();
					return responseJson(200, 'Successfully logged out');
			 }
			 
			 public function forgotAdminPassword(Request $request
			 ): \Illuminate\Http\JsonResponse {
					try {
						  $validated = $request->validate([
								'email' => 'required|email|exists:admins,email'
						  ], [
								'email.required' => 'The email field is required.',
								'email.email'    => 'Please enter a valid email address.',
								'email.exists'   => 'No account found with this email address.'
						  ]);
						  $admin = Admin::where('email', $request->email)->first();
						  
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
						  
						  return responseJson(200, "Reset PIN sent to email");
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
						  return responseJson(422, 'Validation failed', [
								'errors'  => $e->errors(),
								'message' => 'Please check your email address'
						  ]);
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Password reset request failed', [
								'error'   => config('app.debug') ? $e->getMessage()
									 : null,
								'message' => 'Please try again later'
						  ]);
					}
					
			 }
			 
			 public function resetAdminPassword(Request $request
			 ): \Illuminate\Http\JsonResponse {
					try {
						  $validated = $request->validate([
								'email'    => 'required|email|exists:admins,email',
								'pin'      => 'required|digits:4',
								'password' => 'required|string|min:8|confirmed'
						  ], [
								'email.required'    => 'Admin email is required',
								'email.exists'      => 'No admin account found with this email',
								'pin.required'      => 'Verification PIN is required',
								'pin.digits'        => 'PIN must be a 4-digit number',
								'password.required' => 'New password is required'
						  ]);
						  
						  $admin = Admin::where('email', $validated['email'])->first(
						  );
						  
						  // Verify PIN through PinService
						  if (!$this->pinService->verifyPin(
								$admin, $validated['pin'], 'reset'
						  )
						  ) {
								 return responseJson(401, 'Invalid or expired PIN', [
									  'suggestion' => 'Request a new PIN'
								 ]);
						  }
//						  if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $request->password)) {
//								 $e->add(
//									  'password',
//									  'Password must contain at least 1 uppercase, 1 lowercase, 1 number, and 1 special character'
//								 );
//						  }
						  
						  // Update password
						  $admin->update([
								'password' => Hash::make($validated['password'])
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
			 
			 protected function registerSuperAdmin(Request $request): JsonResponse
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
								'phone'    => 'required|string|max_digits:11|unique:admins,phone',
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
						  
						  // Create admin if validation passes
						  $admin = Admin::create([
								'name'        => $validated['fullName'],
								'email'       => $validated['email'],
								'phone'       => $validated['phone'],
								'password'    => Hash::make($validated['password']),
								'is_approved' => 1, // Default unapproved
								'company_id'  => null,
						  ]);
						  
						  $pinResult = $this->pinService->generateAndSendPin(
								$admin, 'verification'
						  );
						  
						  if (!$pinResult['email_sent']) {
								 $admin->delete();
								 return responseJson(
									  500, 'Registration failed - email not sent'
								 );
						  }
						  
						  // Assign a pending role and basic permissions
						  $admin->assignRole('super-admin');
						  
						  return responseJson(
								201,
								'Admin created. Verify email to activate. And  Your account requires super-admin approval'
								, [
									 'id'    => $admin->id,
									 'email' => $admin->email,
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
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 protected function respondWithToken($token, $data
			 ): \Illuminate\Http\JsonResponse {
					return responseJson(
						 200, 'Authenticated',
						 [
							  'token' => ' type: bearer  ' . $token,
							  'admin' => $data
						 ]
					);
					
			 }
	  }