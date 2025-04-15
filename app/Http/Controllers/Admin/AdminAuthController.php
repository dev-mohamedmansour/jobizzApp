<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Admin;
	  use App\Models\PasswordResetPin;
	  use App\Models\User;
	  use App\Notifications\AdminRegistrationPending;
	  use App\Services\PinService;
	  use Illuminate\Http\Request;
//	  use Illuminate\Support\Facades\Auth;
	  use Illuminate\Support\Facades\Auth;
	  use Illuminate\Support\Facades\Hash;
	  use Illuminate\Support\Facades\Notification;
	  use Illuminate\Validation\ValidationException;
	  use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
	  
	  use Illuminate\Support\Facades\Validator;
	  
	  class AdminAuthController extends Controller
	  {
			 protected $pinService;
			 
			 public function __construct(PinService $pinService)
			 {
					$this->pinService = $pinService;
			 }
			 
			 public function register(Request $request): \Illuminate\Http\JsonResponse
			 {
					$request->validate([
						 'name' => 'required|string|max:255',
						 'email' => 'required|email|unique:admins,email|unique:users,email',
						 'phone'    => 'required|string|unique:admins,phone|unique:users,phone',
						 'password' => 'required|string|min:8|confirmed',
					]);
					
					$admin = Admin::create([
						 'name' => $request->name,
						 'email' => $request->email,
						 'phone'=> $request->phone,
						 'password' => Hash::make($request->password),
						 'is_approved' => false, // Default unapproved
						 'company_id' => null
					]);
					
					$pinResult = $this->pinService->generateAndSendPin($admin, 'verification');
					
					if (!$pinResult['email_sent']) {
						  $admin->delete();
						  return responseJson(500, 'Registration failed - email not sent');
					}
					
					// Assign a temporary "pending" role (optional)
					$admin->assignRole('pending');
					
					// Notify all super-admins
					$superAdmins = Admin::role('super-admin')->get();
					
					Notification::send($superAdmins, new AdminRegistrationPending($admin));
					
					return responseJson(201,
						 'Admin created. Verify email to activate.
						 And  Your account requires super-admin approval'
					,[
						 'id'=>$admin->id,
						 'email'=>$admin->email,
						 ]);
			 }
			 
			 public function verifyEmail(Request $request): \Illuminate\Http\JsonResponse
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
									  200, 'Email verified successfully', [
											$admin->verified_email
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
			 
			 public function approve(Admin $admin): \Illuminate\Http\JsonResponse
			 {
					if (!auth()->user()->hasRole('super-admin')) abort(403);
					
					$admin->update([
						 'is_approved' => true,
						 'approved_by' => auth()->id()
					]);
					
					// Assign default role (e.g., 'admin')
					$admin->assignRole('admin');
					
					return responseJson(200, 'Admin approved');
			 }
			 
			 public function createSubAdmin(Request $request) {
					$request->validate([
						 'email' => 'required|email|unique:admins',
						 'password' => 'required|min:8',
						 'role' => 'required|in:hr,coo'
					]);
					
					$companyId = auth('admin')->user()->company_id;
					
					$subAdmin = Admin::create([
						 'email' => $request->email,
						 'password' => bcrypt($request->password),
						 'company_id' => $companyId,
						 'is_approved' => true // Auto-approve sub-admins
					]);
					
					$subAdmin->assignRole($request->role);
					
					return responseJson(201,'Sub-admin created');
			 }
			 public function login(Request $request): \Illuminate\Http\JsonResponse
			 {
					$credentials = $request->validate([
						 'email' => 'required|email',
						 'password' => 'required|string'
					]);
					
					if (!$token = auth('admin')->attempt($credentials)) {
						  return responseJson(401, 'Invalid credentials');
					}
					
					
					
					$admin = auth('admin')->user();
					
					if (!$admin->confirmed_email) {
						  auth('admin')->logout();
						  return responseJson(403, 'Email not verified');
					}
					
					return $this->respondWithToken($token,$admin);
			 }
			 
			 public function logout(): \Illuminate\Http\JsonResponse
			 {
					auth('admin')->logout();
					return responseJson(200, 'Successfully logged out');
			 }
			 
			 public function forgotAdminPassword(Request $request): \Illuminate\Http\JsonResponse
			 {
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
			 
			 public function resetAdminPassword(Request $request): \Illuminate\Http\JsonResponse
			 {
					try {
						  $validated = $request->validate([
								'email' => 'required|email|exists:admins,email',
								'pin' => 'required|digits:4',
								'password' => 'required|string|min:8|confirmed'
						  ], [
								'email.required' => 'Admin email is required',
								'email.exists' => 'No admin account found with this email',
								'pin.required' => 'Verification PIN is required',
								'pin.digits' => 'PIN must be a 4-digit number',
								'password.required' => 'New password is required'
						  ]);
						  
						  $admin = Admin::where('email', $validated['email'])->first();
						  
						  // Verify PIN through PinService
						  if (!$this->pinService->verifyPin($admin, $validated['pin'], 'reset')) {
								 return responseJson(401, 'Invalid or expired PIN', [
									  'suggestion' => 'Request a new PIN'
								 ]);
						  }
						  
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
			 protected function respondWithToken($token,$data): \Illuminate\Http\JsonResponse
			 {
					return responseJson(200, 'Authenticated',
						 [
						 'token' => ' type: bearer  '.$token,
						 'admin'=>$data
					]);
					
			 }
	  }