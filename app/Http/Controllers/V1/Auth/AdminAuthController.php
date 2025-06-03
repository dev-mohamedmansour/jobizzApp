<?php
	  
	  namespace App\Http\Controllers\V1\Auth;
	  
	  use App\Http\Controllers\Controller;
	  use App\Http\Requests\AdminLoginRequest;
	  use App\Http\Requests\AdminRegistrationRequest;
	  use App\Http\Requests\CreateSubAdminRequest;
	  use App\Http\Requests\NewPasswordRequest;
	  use App\Http\Requests\RequestPasswordResetRequest;
	  use App\Http\Requests\ResendEmailRequest;
	  use App\Http\Requests\VerifyEmailRequest;
	  use App\Mail\SubAdminCredentialsMail;
	  use App\Models\Admin;
	  use App\Models\PasswordResetPin;
	  use App\Notifications\AdminApprovedNotification;
	  use App\Services\PinService;
	  use Cassandra\Exception\ValidationException;
	  use Exception;
	  use Illuminate\Database\Eloquent\ModelNotFoundException;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Support\Facades\Cache;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Hash;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Mail;
	  use Illuminate\Support\Facades\Storage;
	  use Illuminate\Support\Str;
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
			 
			 /**
			  * Register a new super-admin.
			  */
			 public function superAdminSignUp(AdminRegistrationRequest $request
			 ): JsonResponse {
					return $this->registerAdmin($request, true);
			 }
			 
			 /**
			  * Register a new admin (super-admin or regular admin).
			  */
			 private function registerAdmin(AdminRegistrationRequest $request,
				  bool $isSuperAdmin = false
			 ): JsonResponse {
					try {
						  $validated = $request->validated();
						  $validated['photo'] = $request->hasFile('photo')
								? Storage::disk('public')->url(
									 $request->file('photo')->store(
										  'admin_images', 'public'
									 )
								)
								: 'https://jobizaa.com/still_images/userDefault.jpg';
						  
						  $admin = Admin::create([
								'name'        => $validated['fullName'],
								'email'       => $validated['email'],
								'phone'       => $validated['phone'],
								'photo'       => $validated['photo'],
								'password'    => Hash::make($validated['password']),
								'is_approved' => true,
								'company_id'  => null,
						  ]);
						  
						  // Generate and send PIN
						  $pinResult = $this->pinService->generateAndSendPin(
								$admin, 'verification'
						  );
						  if (!$pinResult['email_sent']) {
								 $admin->delete();
								 return responseJson(
									  500, 'Registration failed', 'Email not sent'
								 );
						  }
						  $admin->update(['approved_by' => $admin->id]);
						  // Assign role
						  $role = $isSuperAdmin ? 'super-admin' : 'admin';
						  $admin->assignRole($role);
						  $message = $isSuperAdmin
								? 'Super-admin created. Verify email to activate.'
								: 'Admin created. Verify email to activate.';
						  
						  return responseJson(201, $message, [
								'id'       => $admin->id,
								'fullName' => $admin->name,
								'email'    => $admin->email,
						  ]);
					} catch (Exception $e) {
						  Log::error('Admin Registration Error', [
								'message' => $e->getMessage(),
								'user_id' => auth('admin')->id() ??
									 'something went wrong',
								'request' => $request->all(),
						  ]);
						  $errorMessage = config('app.debug')
								? "Server error: {$e->getMessage()}"
								: 'Server error: Something went wrong.';
						  return responseJson(500, 'Server error', $errorMessage);
					}
			 }
			 
			 /**
			  * Register a new regular admin.
			  */
			 public function register(AdminRegistrationRequest $request
			 ): JsonResponse {
					return $this->registerAdmin($request, false);
			 }
			 
			 /**
			  * Verify an admin's email using a PIN.
			  */
			 public function verifyEmail(VerifyEmailRequest $request): JsonResponse
			 {
					try {
						  $validated = $request->validated();
						  $admin = Admin::where('email', $validated['email'])->first(
						  );
						  if (!$admin) {
								 return responseJson(
									  404, 'Admin not found',
									  'Please check your email or register first.'
								 );
						  }
						  
						  if ($this->pinService->verifyPin(
								$admin, $validated['pin_code'], 'verification'
						  )
						  ) {
								 if (!$admin->hasVerifiedEmail()) {
										$admin->markEmailAsVerified();
								 }
								 return responseJson(
									  200, 'Email verified successfully'
								 );
						  }
						  
						  return responseJson(400, 'Error', 'Invalid PIN code');
					} catch (Exception $e) {
						  Log::error('Email Verification Error', [
								'message' => $e->getMessage(),
								'user_id' => auth('admin')->id() ?? 'something went wrong',
								'request' => $request->all(),
						  ]);
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong.'
						  );
					}
			 }
			 
			 public function resendEmail(ResendEmailRequest $request): JsonResponse
			 {
					try {
						  $validated = $request->validated();
						  $admin = Admin::where('email', $validated['email'])
								->firstOrFail();
						  
						  if ($admin->hasVerifiedEmail()) {
								 return responseJson(
									  400, 'Invalid request', 'Email already verified'
								 );
						  }
						  
						  $pinResult = $this->pinService->generateAndSendPin(
								$admin, 'verification'
						  );
						  
						  if (!$pinResult['email_sent']) {
								 return responseJson(
									  500,  'Email not sent','Failed to send verification email'
								 );
						  }
						  
						  return responseJson(
								200, 'Verification PIN sent',
								'Please check your email for verification PIN, including your spam folder.'
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Admin not found');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (Exception $e) {
						  Log::error('Resend email error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong. Please try again later'
						  );
					}
			 }
			 
			 /**
			  * Approve a pending admin (super-admin only).
			  */
			 public function approve(int $pendingAdmin): JsonResponse
			 {
					try {
						  if (!auth('admin')->user()->hasRole('super-admin')) {
								 return responseJson(
									  403, 'Unauthorized',
									  'Only super-admins can approve.'
								 );
						  }
						  
						  $admin = Admin::find($pendingAdmin);
						  if (!$admin) {
								 return responseJson(404, 'Not found', 'Admin not found.');
						  }
						  
						  if ($admin->is_approved) {
								 return responseJson(
									  403, 'Forbidden', 'This account is already approved.'
								 );
						  }
						  
						  DB::transaction(function () use ($admin) {
								 $admin->update([
									  'is_approved' => true,
									  'approved_by' => auth('admin')->id(),
								 ]);
								 
								 $admin->removeRole('pending');
								 $admin->revokePermissionTo('access-pending');
								 $admin->assignRole('admin');
								 $admin->givePermissionTo(
									  ['manage-own-company', 'manage-company-jobs',
										'manage-company-admins']
								 );
								 
								 $admin->notify(new AdminApprovedNotification());
						  });
						  
						  return responseJson(200, 'Admin approved successfully', [
								'admin_id'   => $admin->id,
								'admin_name' => $admin->name,
								'new_role'   => 'admin',
						  ]);
					} catch (RoleAlreadyExists|PermissionAlreadyExists $e) {
						  return responseJson(500, 'Error', $e->getMessage());
					} catch (Exception $e) {
						  Log::error('Admin Approval Error', [
								'message'  => $e->getMessage(),
								'user_id'  => auth('admin')->id() ?? 'something went wrong',
								'admin_id' => $pendingAdmin,
						  ]);
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong.'
						  );
					}
			 }
			 
			 /**
			  * Create a sub-admin for a company.
			  */
			 public function createSubAdmin(CreateSubAdminRequest $request): JsonResponse {
					try {
						  $validated = $request->validated();
						  $admin = auth('admin')->user();
						  if (empty($admin->company_id)) {
								 return responseJson(
									  403, 'Forbidden',
									  'You must create a company before adding sub-admins.'
								 );
						  }
						  $subAdminCount = Cache::remember(
								"sub_admin_count_{$admin->company_id}", 60,
								function () use ($admin) {
									  return Admin::where(
											'company_id', $admin->company_id
									  )
											->where('id', '!=', $admin->id)
											->count();
								}
						  );
						  if ($subAdminCount >= 8) {
								 return responseJson(
									  403, 'Forbidden',
									  'You can only create up to 8 sub-admins.'
								 );
						  }
						  $password = Str::password(
								12, letters: true, numbers: true, symbols: true
						  );
						  $subAdmin = null;
						  DB::transaction(
								function () use (
									 $validated, $admin, &$subAdmin, $password
								) {
									  $subAdmin = Admin::create([
											'name'              => $validated['fullName'],
											'email'             => $validated['email'],
											'phone'             => $validated['phone'],
											'password'          => Hash::make($password),
											'company_id'        => $admin->company_id,
											'is_approved'       => true,
											'approved_by'       => $admin->id,
											'confirmed_email'   => true,
											'email_verified_at' => now(),
									  ]);
									  $subAdmin->markEmailAsVerified();
									  $subAdmin->assignRole($validated['role']);
								}
						  );
						  Cache::forget("sub_admin_count_{$admin->company_id}");
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
					} catch (Exception $e) {
						  Log::error('Sub-Admin Creation Error', [
								'message' => $e->getMessage(),
								'user_id' => auth('admin')->id() ?? 'something went wrong',
								'request' => $request->all(),
						  ]);
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong.'
						  );
					}
			 }
			 
			 /**
			  * Log in an admin.
			  */
			 public function login(AdminLoginRequest $request): JsonResponse
			 {
					try {
						  $validated = $request->validated();
						  
						  if (!$token = auth('admin')->attempt($validated)) {
								 return responseJson(
									  401, 'Unauthorized', 'Email or password invalid.'
								 );
						  }
						  $admin = auth('admin')->user();
						  if (!$admin->hasVerifiedEmail()) {
								 auth('admin')->logout();
								 return responseJson(
									  403, 'Forbidden',
									  'Please verify your email address before logging in.'
								 );
						  }
						  if (!$admin->is_approved) {
								 auth('admin')->logout();
								 return responseJson(
									  403, 'Forbidden',
									  'Account pending approval. Please contact the administrator.'
								 );
						  }
						  return responseJson(200, 'Login successful', [
								'token'       => $token,
								'id'          => $admin->id,
								'fullName'    => $admin->name,
								'email'       => $admin->email,
								'roles'       => $admin->getRoleNames(),
								'permissions' => $admin->getAllPermissions()->pluck(
									 'name'
								),
						  ]);
					} catch (Exception $e) {
						  Log::error('Login Error', [
								'message' => $e->getMessage(),
								'user_id' => auth('admin')->id() ?? 'something went wrong',
								'request' => $request->all(),
						  ]);
						  $errorMessage = config('app.debug')
								? "Server error: {$e->getMessage()}"
								: 'Server error: Something went wrong.';
						  return responseJson(500, 'Server error', $errorMessage);
					}
			 }
			 
			 /**
			  * Log out an admin.
			  */
			 public function logout(): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(
									  401, 'Unauthorized', 'No authenticated user found.'
								 );
						  }
						  
						  auth('admin')->logout();
						  JWTAuth::invalidate(JWTAuth::getToken());
						  session()->flush();
						  
						  return responseJson(200, 'Successfully logged out');
					} catch (TokenInvalidException $e) {
						  return responseJson(
								401, 'Unauthorized', 'Invalid authentication token.'
						  );
					} catch (JWTException $e) {
						  return responseJson(
								500, 'Server error', 'Could not invalidate token.'
						  );
					} catch (Exception $e) {
						  Log::error('Logout Error', [
								'message' => $e->getMessage(),
								'user_id' => auth('admin')->id() ?? 'something went wrong',
						  ]);
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Logout failed due to server error.'
						  );
					}
			 }
			 
			 /**
			  * Request a password reset PIN.
			  */
			 public function requestPasswordReset(RequestPasswordResetRequest $request
			 ): JsonResponse {
					try {
						  $validated = $request->validated();
						  $admin = Admin::where('email', $validated['email'])->first(
						  );
						  PasswordResetPin::where('email', $validated['email'])
								->delete();
						  $pinResult = $this->pinService->generateAndSendPin(
								$admin, 'reset'
						  );
						  if (!$pinResult['email_sent']) {
								 throw new Exception(
									  'Failed to send password reset email.'
								 );
						  }
						  return responseJson(
								200, 'Reset PIN sent successfully to email.'
						  );
					} catch (Exception $e) {
						  Log::error('Password Reset Request Error', [
								'message' => $e->getMessage(),
								'user_id' => auth('admin')->id() ?? 'something went wrong',
								'request' => $request->all(),
						  ]);
						  $errorMessage = config('app.debug')
								? "Server error: {$e->getMessage()}"
								: 'Password reset request failed. Please try again later.';
						  return responseJson(500, 'Server error', $errorMessage);
					}
			 }
			 
			 /**
			  * Reset the password using a PIN.
			  */
			 public function newPassword(NewPasswordRequest $request): JsonResponse
			 {
					try {
						  $validated = $request->validated();
						  $admin = Admin::where('email', $validated['email'])->first(
						  );
						  if (!$this->pinService->verifyPin(
								$admin, $validated['pinCode'], 'reset'
						  )
						  ) {
								 return responseJson(
									  401, 'Unauthorized',
									  'Expired PIN. Request a new PIN.'
								 );
						  }
						  $admin->update(
								['password' => Hash::make($validated['newPassword'])]
						  );
						  PasswordResetPin::where('email', $admin->email)
								->where('type', 'admin')
								->delete();
						  return responseJson(200, 'Password reset successfully', [
								'email' => $admin->email,
						  ]);
					} catch (Exception $e) {
						  Log::error('Password Reset Error', [
								'message' => $e->getMessage(),
								'user_id' => auth('admin')->id() ?? 'something went wrong',
								'request' => $request->all(),
						  ]);
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Password reset failed.'
						  );
					}
			 }
	  }