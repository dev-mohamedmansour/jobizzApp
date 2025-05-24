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
	  use Illuminate\Support\Facades\Cache;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Hash;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Mail;
	  use Illuminate\Support\Facades\Storage;
	  use Illuminate\Support\Str;
	  use Illuminate\Validation\ValidationException;
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
			  * Common validation rules for admin registration.
			  */
			 private function getAdminValidationRules(bool $isCreate = true): array
			 {
					$rules = [
						 'fullName' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
						 'email' => [
							  'required',
							  'string',
							  'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
							  'unique:admins,email',
							  'unique:users,email',
							  'ascii',
						 ],
						 'photo' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
						 'phone' => ['required', 'string', 'max_digits:11', 'unique:admins,phone'],
						 'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^[a-zA-Z0-9@#$%^&*!]+$/'],
					];
					
					if (!$isCreate) {
						  $rules['password'] = ['sometimes', 'string', 'min:8', 'confirmed', 'regex:/^[a-zA-Z0-9@#$%^&*!]+$/'];
						  $rules['email'] = array_filter($rules['email'], fn($rule) => !str_contains($rule, 'unique'));
						  $rules['phone'] = array_filter($rules['phone'], fn($rule) => !str_contains($rule, 'unique'));
					}
					
					return $rules;
			 }
			 
			 /**
			  * Common validation error messages.
			  */
			 private function getValidationMessages(): array
			 {
					return [
						 'fullName.required' => 'The name field is required.',
						 'fullName.regex' => 'Name must contain only English letters and spaces.',
						 'email.required' => 'The email field is required.',
						 'email.regex' => 'Invalid email format. Use English characters only.',
						 'email.ascii' => 'Email must contain only English characters.',
						 'email.unique' => 'This email is already registered.',
						 'phone.required' => 'The phone field is required.',
						 'phone.max_digits' => 'Phone number cannot exceed 11 digits.',
						 'phone.unique' => 'This phone number is already registered.',
						 'photo.image' => 'The photo must be an image.',
						 'photo.mimes' => 'The photo must be a file of type: jpeg, png, jpg, gif, svg.',
						 'photo.max' => 'The photo cannot exceed 2MB in size.',
						 'password.required' => 'The password field is required.',
						 'password.confirmed' => 'Password confirmation does not match.',
						 'password.min' => 'Password must be at least 8 characters.',
						 'password.regex' => 'Password can only contain English letters, numbers, and special symbols.',
					];
			 }
			 
			 /**
			  * Handle photo upload with default fallback.
			  */
			 private function handlePhotoUpload(Request $request, array $validated, ?string $existingPhoto = null): array
			 {
					if ($request->hasFile('photo')) {
						  if ($existingPhoto && Storage::disk('public')->exists($this->normalizePath($existingPhoto))) {
								 Storage::disk('public')->delete($this->normalizePath($existingPhoto));
						  }
						  $path = $request->file('photo')->store('admin_images', 'public');
						  $validated['photo'] = Storage::disk('public')->url($path);
					} elseif (!$existingPhoto) {
						  $validated['photo'] = 'https://jobizaa.com/still_images/userDefault.jpg';
					}
					
					return $validated;
			 }
			 
			 /**
			  * Normalize a file path by removing URL prefix.
			  */
			 private function normalizePath(string $path): string
			 {
					return str_replace(Storage::disk('public')->url(''), '', $path);
			 }
			 
			 /**
			  * Register a super-admin.
			  */
			 public function superAdminSignUp(Request $request): JsonResponse
			 {
					try {
						  $validated = $request->validate($this->getAdminValidationRules(), $this->getValidationMessages());
						  $validated = $this->handlePhotoUpload($request, $validated);
						  
						  $admin = DB::transaction(function () use ($validated) {
								 $admin = Admin::create([
									  'name' => $validated['fullName'],
									  'email' => $validated['email'],
									  'phone' => $validated['phone'],
									  'photo' => $validated['photo'],
									  'password' => Hash::make($validated['password']),
									  'is_approved' => true,
									  'company_id' => null,
									  'approved_by' => null,
								 ]);
								 
								 $pinResult = $this->pinService->generateAndSendPin($admin, 'verification');
								 if (!$pinResult['email_sent']) {
										throw new \Exception('Failed to send verification email');
								 }
								 
								 $admin->update(['approved_by' => $admin->id]);
								 $admin->assignRole('super-admin');
								 
								 return $admin;
						  });
						  
						  return responseJson(201, 'Super-admin created. Verify email to activate.', [
								'id' => $admin->id,
								'fullName' => $admin->name,
								'email' => $admin->email,
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (\Exception $e) {
						  Log::error('Super-admin signup error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Registration failed.');
					}
			 }
			 
			 /**
			  * Register a regular admin.
			  */
			 public function register(Request $request): JsonResponse
			 {
					try {
						  $validated = $request->validate($this->getAdminValidationRules(), $this->getValidationMessages());
						  $validated = $this->handlePhotoUpload($request, $validated);
						  
						  $admin = DB::transaction(function () use ($validated) {
								 $admin = Admin::create([
									  'name' => $validated['fullName'],
									  'email' => $validated['email'],
									  'phone' => $validated['phone'],
									  'photo' => $validated['photo'],
									  'password' => Hash::make($validated['password']),
									  'is_approved' => true, // Changed to true for simplicity
									  'company_id' => null,
									  'approved_by' => null,
								 ]);
								 
								 $pinResult = $this->pinService->generateAndSendPin($admin, 'verification');
								 if (!$pinResult['email_sent']) {
										throw new \Exception('Failed to send verification email');
								 }
								 
								 $admin->update(['approved_by' => $admin->id]);
								 $admin->assignRole('admin');
								 
								 return $admin;
						  });
						  
						  return responseJson(201, 'Admin created. Verify email to activate.', [
								'id' => $admin->id,
								'fullName' => $admin->name,
								'email' => $admin->email,
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (\Exception $e) {
						  Log::error('Admin registration error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Registration failed.');
					}
			 }
			 
			 /**
			  * Verify admin email.
			  */
			 public function verifyEmail(Request $request): JsonResponse
			 {
					try {
						  $validated = $request->validate([
								'email' => [
									 'required',
									 'string',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
									 'exists:admins,email',
									 'ascii',
								],
								'pin_code' => [
									 'required',
									 'digits:6',
									 'numeric',
									 'not_in:000000,111111,123456,654321',
								],
						  ], [
								'email.required' => 'The email field is required.',
								'email.regex' => 'Invalid email format. Use English characters only.',
								'email.exists' => 'Email not found.',
								'email.ascii' => 'Email must contain only English characters.',
								'pin_code.required' => 'PIN code is required.',
								'pin_code.digits' => 'PIN must be exactly 6 digits.',
								'pin_code.numeric' => 'PIN must contain only numbers.',
								'pin_code.not_in' => 'This PIN is too common and insecure.',
						  ]);
						  
						  $admin = Admin::where('email', $validated['email'])->firstOrFail();
						  
						  if ($this->pinService->verifyPin($admin, $validated['pin_code'], 'verification')) {
								 if (!$admin->hasVerifiedEmail()) {
										$admin->markEmailAsVerified();
										Cache::store('redis')->forget("admin_{$admin->id}_profile");
								 }
								 return responseJson(200, 'Email verified successfully');
						  }
						  
						  return responseJson(400, 'Invalid PIN code');
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (\Exception $e) {
						  Log::error('Email verification error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Verification failed.');
					}
			 }
			 
			 /**
			  * Approve a pending admin.
			  */
			 public function approve($pendingAdmin): JsonResponse
			 {
					try {
						  $currentAdmin = auth('admin')->user();
						  if (!$currentAdmin || !$currentAdmin->hasRole('super-admin')) {
								 return responseJson(403, 'Unauthorized', 'Only super-admins can approve.');
						  }
						  
						  $admin = Admin::findOrFail($pendingAdmin);
						  if ($admin->is_approved) {
								 return responseJson(403, 'Already approved', 'This account is already approved.');
						  }
						  
						  DB::transaction(function () use ($admin, $currentAdmin) {
								 $admin->update([
									  'is_approved' => true,
									  'approved_by' => $currentAdmin->id,
								 ]);
								 
								 $admin->removeRole('pending');
								 $admin->revokePermissionTo('access-pending');
								 $admin->assignRole('admin');
								 $admin->givePermissionTo(['manage-own-company', 'manage-company-jobs', 'manage-company-admins']);
								 
								 $admin->notify(new AdminApprovedNotification());
								 Cache::store('redis')->forget("admin_{$admin->id}_profile");
						  });
						  
						  return responseJson(200, 'Admin approved successfully', [
								'admin_id' => $admin->id,
								'admin_name' => $admin->name,
								'new_role' => 'admin',
						  ]);
					} catch (RoleAlreadyExists | PermissionAlreadyExists $e) {
						  return responseJson(500, 'Configuration error', $e->getMessage());
					} catch (\Exception $e) {
						  Log::error('Admin approval error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Approval failed.');
					}
			 }
			 
			 /**
			  * Create a sub-admin.
			  */
			 public function createSubAdmin(Request $request): JsonResponse
			 {
					try {
						  $validated = $request->validate([
								'fullName' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
								'email' => [
									 'required',
									 'string',
									 'email',
									 'max:255',
									 'unique:admins,email',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
								],
								'phone' => ['required', 'string', 'max_digits:11', 'unique:admins,phone'],
								'role' => ['required', 'in:hr,coo'],
						  ], [
								'fullName.regex' => 'Name must only contain letters and spaces.',
								'email.unique' => 'This email is already registered.',
								'phone.unique' => 'This phone number is already registered.',
						  ]);
						  
						  $admin = auth('admin')->user();
						  if (!$admin instanceof Admin || !$admin->hasPermissionTo('manage-company-admins')) {
								 return responseJson(403, 'Unauthorized', 'Not allowed to add sub-admin.');
						  }
						  
						  if (empty($admin->company_id)) {
								 return responseJson(403, 'Forbidden', 'You must create a company first.');
						  }
						  
						  $cacheKey = "company_{$admin->company_id}_sub_admins_count";
						  $subAdminCount = Cache::store('redis')->remember($cacheKey, now()->addMinutes(5), fn() =>
						  Admin::where('company_id', $admin->company_id)
								->where('id', '!=', $admin->id)
								->count()
						  );
						  
						  if ($subAdminCount >= 8) {
								 return responseJson(403, 'Forbidden', 'Maximum of 8 sub-admins allowed.');
						  }
						  
						  $password = Str::random(12);
						  $subAdmin = DB::transaction(function () use ($validated, $admin, $password) {
								 $subAdmin = Admin::create([
									  'name' => $validated['fullName'],
									  'email' => $validated['email'],
									  'phone' => $validated['phone'],
									  'password' => Hash::make($password),
									  'company_id' => $admin->company_id,
									  'is_approved' => true,
									  'approved_by' => $admin->id,
									  'email_verified_at' => now(),
								 ]);
								 
								 $subAdmin->markEmailAsVerified();
								 $subAdmin->assignRole($validated['role']);
								 
								 Mail::to($validated['email'])->queue(new SubAdminCredentialsMail(
									  $validated['fullName'],
									  $validated['email'],
									  $password,
									  $validated['role']
								 ));
								 
								 return $subAdmin;
						  });
						  
						  Cache::store('redis')->forget($cacheKey);
						  
						  return responseJson(201, 'Sub-admin created and email queued.', [
								'sub_admin_id' => $subAdmin->id,
								'sub_admin_name' => $subAdmin->name,
								'sub_admin_email' => $subAdmin->email,
								'sub_admin_role' => $validated['role'],
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (\Exception $e) {
						  Log::error('Sub-admin creation error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Sub-admin creation failed.');
					}
			 }
			 
			 /**
			  * Admin login.
			  */
			 public function login(Request $request): JsonResponse
			 {
					try {
						  $validated = $request->validate([
								'email' => [
									 'required',
									 'string',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
									 'exists:admins,email',
									 'ascii',
								],
								'password' => ['required', 'string', 'min:8', 'regex:/^[a-zA-Z0-9@#$%^&*!]+$/'],
						  ], [
								'email.required' => 'The email field is required.',
								'email.exists' => 'Account not found.',
								'email.regex' => 'Invalid email format. Use English characters only.',
								'email.ascii' => 'Email must contain only English characters.',
								'password.required' => 'The password field is required.',
								'password.min' => 'Password must be at least 8 characters.',
								'password.regex' => 'Password contains invalid characters.',
						  ]);
						  
						  $cacheKey = "admin_login_attempts_{$validated['email']}";
						  $attempts = Cache::store('redis')->get($cacheKey, 0);
						  
						  if ($attempts >= 5) {
								 return responseJson(429, 'Too many login attempts', 'Please wait 15 minutes before trying again.');
						  }
						  
						  if (!$token = auth('admin')->attempt($validated)) {
								 Cache::store('redis')->increment($cacheKey);
								 Cache::store('redis')->expire($cacheKey, 900); // 15 minutes
								 return responseJson(401, 'Unauthorized', 'Invalid email or password.');
						  }
						  
						  $admin = auth('admin')->user();
						  if (!$admin->hasVerifiedEmail()) {
								 auth('admin')->logout();
								 return responseJson(403, 'Forbidden', 'Please verify your email address.');
						  }
						  
						  if (!$admin->is_approved) {
								 auth('admin')->logout();
								 return responseJson(403, 'Forbidden', 'Account pending approval.');
						  }
						  
						  Cache::store('redis')->forget($cacheKey);
						  
						  return responseJson(200, 'Login successful', [
								'token' => $token,
								'id' => $admin->id,
								'fullName' => $admin->name,
								'email' => $admin->email,
								'roles' => $admin->getRoleNames(),
								'permissions' => $admin->getAllPermissions()->pluck('name'),
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (\Exception $e) {
						  Log::error('Login error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Login failed.');
					}
			 }
			 
			 /**
			  * Admin logout.
			  */
			 public function logout(): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthorized', 'No authenticated user found.');
						  }
						  
						  JWTAuth::invalidate(JWTAuth::getToken());
						  auth('admin')->logout();
						  session()->flush();
						  
						  return responseJson(200, 'Successfully logged out');
					} catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException $e) {
						  return responseJson(401, 'Unauthorized', 'Invalid authentication token.');
					} catch (\Exception $e) {
						  Log::error('Logout error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Logout failed.');
					}
			 }
			 
			 /**
			  * Request a password reset PIN.
			  */
			 public function requestPasswordReset(Request $request): JsonResponse
			 {
					try {
						  $validated = $request->validate([
								'email' => [
									 'required',
									 'string',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
									 'exists:admins,email',
									 'ascii',
								],
						  ], [
								'email.required' => 'The email field is required.',
								'email.exists' => 'Account not found.',
								'email.regex' => 'Invalid email format. Use English characters only.',
								'email.ascii' => 'Email must contain only English characters.',
						  ]);
						  
						  $cacheKey = "password_reset_attempts_{$validated['email']}";
						  $attempts = Cache::store('redis')->get($cacheKey, 0);
						  
						  if ($attempts >= 3) {
								 return responseJson(429, 'Too many attempts', 'Please wait 1 hour before requesting another PIN.');
						  }
						  
						  $admin = Admin::where('email', $validated['email'])->firstOrFail();
						  
						  DB::transaction(function () use ($validated, $admin, $cacheKey) {
								 PasswordResetPin::where('email', $validated['email'])->delete();
								 
								 $pinResult = $this->pinService->generateAndSendPin($admin, 'reset');
								 if (!$pinResult['email_sent']) {
										throw new \Exception('Failed to send password reset email');
								 }
								 
								 Cache::store('redis')->increment($cacheKey);
								 Cache::store('redis')->expire($cacheKey, 3600); // 1 hour
						  });
						  
						  return responseJson(200, 'Reset PIN sent successfully to email');
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (\Exception $e) {
						  Log::error('Password reset request error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Password reset request failed.');
					}
			 }
			 
			 /**
			  * Reset password with PIN.
			  */
			 public function newPassword(Request $request): JsonResponse
			 {
					try {
						  $validated = $request->validate([
								'email' => [
									 'required',
									 'string',
									 'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
									 'exists:admins,email',
									 'ascii',
								],
								'pinCode' => [
									 'required',
									 'digits:6',
									 'numeric',
									 'not_in:000000,111111,123456,654321',
								],
								'newPassword' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^[a-zA-Z0-9@#$%^&*!]+$/'],
						  ], [
								'email.required' => 'The email field is required.',
								'email.exists' => 'Account not found.',
								'email.regex' => 'Invalid email format. Use English characters only.',
								'email.ascii' => 'Email must contain only English characters.',
								'pinCode.required' => 'PIN code is required.',
								'pinCode.digits' => 'PIN must be exactly 6 digits.',
								'pinCode.numeric' => 'PIN must contain only numbers.',
								'pinCode.not_in' => 'This PIN is too common and insecure.',
								'newPassword.required' => 'New password is required.',
								'newPassword.confirmed' => 'Password confirmation does not match.',
								'newPassword.min' => 'Password must be at least 8 characters.',
								'newPassword.regex' => 'Password contains invalid characters.',
						  ]);
						  
						  $admin = Admin::where('email', $validated['email'])->firstOrFail();
						  
						  if (!$this->pinService->verifyPin($admin, $validated['pinCode'], 'reset')) {
								 return responseJson(401, 'Unauthorized', 'Invalid or expired PIN. Request a new PIN.');
						  }
						  
						  DB::transaction(function () use ($admin, $validated) {
								 $admin->update(['password' => Hash::make($validated['newPassword'])]);
								 PasswordResetPin::where('email', $admin->email)->where('type', 'admin')->delete();
								 Cache::store('redis')->forget("admin_{$admin->id}_profile");
						  });
						  
						  return responseJson(200, 'Password reset successfully', ['email' => $admin->email]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (\Exception $e) {
						  Log::error('Password reset error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Password reset failed.');
					}
			 }
	  }