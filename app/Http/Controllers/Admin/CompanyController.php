<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Admin;
	  use App\Models\Company;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Storage;
	  
	  class CompanyController extends Controller
	  {
			 public function index(): JsonResponse
			 {
					try {
						  // Check if the user is authenticated
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  // Determine which guard the user is authenticated with
						  if (auth()->guard('admin')->check()) {
								 $user = auth('admin')->user();
								 if (!$this->isAdminAuthorized($user)) {
										return responseJson(
											 403,
											 'Forbidden: You do not have permission to view this company'
										);
								 }
						  } elseif (!auth()->guard('api')->check()) {
								 // Deny access if the user is authenticated with an unknown guard
								 return responseJson(
									  403,
									  'Forbidden: You do not have permission to view this company'
								 );
						  }
						  
						  $companies = Company::withCount('jobs')->paginate(10);
						  
						  if ($companies->isEmpty()) {
								 return responseJson(404, 'No companies found');
						  }
						  
						  return responseJson(
								200, 'Companies retrieved successfully', $companies
						  );
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 private function isAdminAuthorized($admin): bool
			 {
					// Check if the user is a super-admin
					if ($admin->hasRole('super-admin')) {
						  return true;
					}
					return false;
			 }
			 
			 public function show($id): JsonResponse
			 {
					try {
						  // Check if the user is authenticated
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $company = Company::with('jobs')->find($id);
						  
						  if (!$company) {
								 return responseJson(404, 'Company not found');
						  }
						  
						  // Determine which guard the user is authenticated with
						  if (auth()->guard('admin')->check()) {
								 $user = auth('admin')->user();
								 if (!$this->isAdminAuthorizedToShow($user, $company)) {
										return responseJson(
											 403,
											 'Forbidden: You do not have permission to view this company'
										);
								 }
						  } elseif (!auth()->guard('api')->check()) {
								 // Deny access if the user is authenticated with an unknown guard
								 return responseJson(
									  403,
									  'Forbidden: You do not have permission to view this company'
								 );
						  }
						  
						  // Get active jobs for this company
						  $activeJobs = $company->jobs()
								->activeJobs() // Use the scope defined in the Job model
								->count();
						  
						  // If there are no active jobs, set active_jobs to 0
						  $activeJobs = $activeJobs ?: 0;
						  
						  return responseJson(200, 'Company details retrieved', [
								'company'     => $company,
								'active_jobs' => $activeJobs
						  ]);
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 private function isAdminAuthorizedToShow($admin, $company): bool
			 {
					// Check if the user is a super-admin
					if ($admin->hasRole('super-admin')) {
						  return true;
					}
					// Check if the user is the admin who created the company
					if ($admin->id === $company->admin_id) {
						  return true;
					}
					// Check if the user is an HR or COO associated with the company
					if ($admin->hasAnyRole(['hr', 'coo'])
						 && $admin->company_id === $company->id
					) {
						  return true;
					}
					return false;
			 }
			 
			 public function store(Request $request): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  $validationRules = [];
						  $validationCustomMessages = [];
						  
						  if ($admin->hasRole('super-admin')) {
								 $validationRules = [
									  'admin_id'     => 'required|exists:admins,id',
									  'name'         => 'required|string|max:255|unique:companies',
									  'logo'         => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
									  'description'  => 'required|string|max:1000',
									  'location'     => 'required|string|max:255',
									  'website'      => 'sometimes|url',
									  'size'         => 'sometimes|string|max:255',
									  'hired_people' => 'required|numeric|min:5',
								 ];
						  } else {
								 if (!$admin->hasPermissionTo('manage-own-company')) {
										return responseJson(403, 'Unauthorized');
								 }
								 if ($admin->company) {
										return responseJson(
											 403,
											 'You already have a company and cannot create another one'
										);
								 }
								 
								 $validationRules = [
									  'name'         => 'required|string|max:255|unique:companies',
									  'logo'         => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
									  'description'  => 'required|string|max:1000',
									  'location'     => 'required|string|max:255',
									  'website'      => 'sometimes|url',
									  'size'         => 'sometimes|string|max:255',
									  'hired_people' => 'required|numeric|min:5',
								 ];
						  }
						  
						  // Add custom validation messages
						  $validationCustomMessages = [
								'name.unique'      => 'A company with this name already exists.',
								'name.max'         => 'Company name cannot exceed 255 characters.',
								'logo.image'       => 'The logo must be an image.',
								'logo.mimes'       => 'The logo must be a file of type: jpeg, png, jpg, gif, svg.',
								'logo.max'         => 'The logo cannot exceed 2MB in size.',
								'description.max'  => 'Company description cannot exceed 1000 characters.',
								'location.max'     => 'Location cannot exceed 255 characters.',
								'size.max'         => 'Company size cannot exceed 255 characters.',
								'hired_people.min' => 'Hired people count cannot be negative.',
						  ];
						  
						  // Validate request data
						  $validated = $request->validate(
								$validationRules, $validationCustomMessages
						  );
						  
						  // Handle logo upload
						  if ($request->hasFile('logo')) {
								 $logoPath = $request->file('logo')->store(
									  'company_logos', 'public'
								 );
								 $urlPath =Storage::disk('public')->url($logoPath);
								 
								 $validated['logo'] = $urlPath;
						  } else {
								 // Set default image URL
								 $validated['logo']
									  = 'https://jobizaa.com/still_images/company.png';
						  }
						  
						  // Create company
						  if ($admin->hasRole('super-admin')) {
								 // Check if the specified admin already has a company
								 $targetAdmin = \App\Models\Admin::find(
									  $validated['admin_id']
								 );
								 if ($targetAdmin && $targetAdmin->company) {
										return responseJson(
											 400,
											 'The specified admin already has a company'
										);
								 }
								 $company = Company::create($validated);
								 $targetAdmin->update(['company_id' => $company->id]);
						  } else {
								 $validated['admin_id'] = $admin->id;
								 $company = Company::create($validated);
								 $admin->update([
									  'company_id' => $company->id
								 ]);
						  }
						  
						  // Return success response
						  return responseJson(201, 'Company created successfully', [
								'company'      => $company,
								'hired_people' => $company->hired_people,
						  ]);
						  
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
			 
			 public function update(Request $request, $id
			 ): JsonResponse {
					try {
						  $admin = auth('admin')->user();
						  $company = Company::find($id);
						  
						  if (!$company) {
								 return responseJson(404, 'Company not found');
						  }
						  
						  // Define validation rules based on user role
						  $validationRules = [];
						  
						  if ($admin->hasRole('super-admin')) {
								 $validationRules = [
									  'admin_id'     => 'required|exists:admins,id',
									  'logo'         => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
									  'description'  => 'sometimes|string|max:1000',
									  'location'     => 'sometimes|string|max:255',
									  'website'      => 'sometimes|url',
									  'size'         => 'sometimes|string|max:255',
									  'hired_people' => 'sometimes|nullable|numeric|min:5',
								 ];
						  } else {
								 if (!$admin->hasPermissionTo('manage-own-company')) {
										return responseJson(403, 'Unauthorized');
								 }
								 
								 if ($admin->id !== $company->admin_id) {
										return responseJson(
											 403,
											 'Forbidden: You can only update your own company'
										);
								 }
								 
								 $validationRules = [
									  'logo'         => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
									  'description'  => 'sometimes|string|max:1000',
									  'location'     => 'sometimes|string|max:255',
									  'website'      => 'sometimes|url',
									  'size'         => 'sometimes|string|max:255',
									  'hired_people' => 'sometimes|nullable|numeric|min:5',
								 ];
						  }
						  
						  // Add custom validation messages
						  $validationCustomMessages = [
								'logo.image'       => 'The logo must be an image.',
								'logo.mimes'       => 'The logo must be a file of type: jpeg, png, jpg, gif, svg.',
								'logo.max'         => 'The logo cannot exceed 2MB in size.',
								'description.max'  => 'Company description cannot exceed 1000 characters.',
								'location.max'     => 'Location cannot exceed 255 characters.',
								'size.max'         => 'Company size cannot exceed 255 characters.',
								'hired_people.min' => 'Hired people count cannot be negative.',
						  ];
						  
						  // Validate request data
						  $validated = $request->validate(
								$validationRules, $validationCustomMessages
						  );
						  
						  // Handle logo upload or removal
						  if ($request->hasFile('logo')) {
								 if ($company->logo
									  && Storage::disk('public')->exists(
											$company->logo
									  )
								 ) {
										Storage::disk('public')->delete($company->logo);
								 }
								 $logoPath = $request->file('logo')->store(
									  'company_logos', 'public'
								 );
								 $urlPath =Storage::disk('public')->url($logoPath);
								 $validated['logo'] = $urlPath;
						  }
						  // Get original data before update
						  $originalData = $company->only(
								['logo', 'description', 'location', 'website', 'size',
								 'hired_people']
						  );
						  $newData = $validated;
						  
						  // Check if any data actually changed
						  $changes = array_diff_assoc($newData, $originalData);
						  
						  if (empty($changes)) {
								 return responseJson(200, 'No changes detected', [
									  'company'      => $company,
									  'hired_people' => $company->hired_people,
									  'unchanged'    => true
								 ]);
						  }
						  
						  // Update company
						  if ($admin->hasRole('super-admin')) {
								 if (isset($validated['admin_id'])) {
										// Check if the specified admin already has a company
										$targetAdmin = \App\Models\Admin::find(
											 $validated['admin_id']
										);
										if (!$targetAdmin) {
											  return responseJson(
													404, 'This admin not found'
											  );
										} elseif ($targetAdmin->company
											 && $targetAdmin->company->id !== $company->id
										) {
											  return responseJson(
													400,
													'The specified admin already has a company'
											  );
										}
										$company->update($validated);
								 } else {
										return responseJson(404, 'This admin not found');
								 }
						  } else {
								 // Remove 'admin_id' from validated data if present
								 if (isset($validated['admin_id'])) {
										unset($validated['admin_id']);
								 }
								 $company->update($validated);
						  }
						  
						  // Return success response
						  return responseJson(200, 'Company updated successfully', [
								'company'      => $company,
								'hired_people' => $company->hired_people,
						  ]);
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
						  return responseJson(
								422,
								" validation error",
								$e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage
								= "Server error: Something went wrong. Please try again later.";
						  if (config('app.debug')) {
								 $errorMessage = "Server error: " . $e->getMessage();
						  }
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 public function destroy($id): JsonResponse
			 {
					try {
						  // Check if the user is authenticated
						  if (!auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  $company = Company::find($id);
						  
						  // Check if the company exists
						  if (!$company) {
								 return responseJson(404, 'Company not found');
						  }
						  
						  // Determine authorization
						  if ($admin->hasPermissionTo('manage-all-companies')) {
								 // Super-admins can delete any company
						  } elseif ($admin->hasPermissionTo('manage-own-company')
								&& $company->id === $admin->company_id
						  ) {
								 // Regular admins can only delete their own company
						  } else {
								 return responseJson(
									  403,
									  'Forbidden: You do not have permission to delete this company'
								 );
						  }
						  
						  // Delete associated resources if needed (e.g., jobs)
						  $company->jobs()->delete();

    						// Delete sub-admins associated with the company (excluding the current admin)
						  $subAdmins = Admin::where('company_id', $company->id)
								->where('id', '!=', $admin->id)
								->get();
						  
						  foreach ($subAdmins as $subAdmin) {
								 // Delete sub-admin's photo if it exists
								 if ($subAdmin->photo
									  && Storage::disk('public')->exists(
											$subAdmin->photo
									  )
								 ) {
										Storage::disk('public')->delete($subAdmin->photo);
								 }
						  }
						  
						  Admin::where('company_id', $company->id)
								->where('id', '!=', $admin->id)
								->delete();
						  
						  // Update the current admin's company_id to null if it matches the company being deleted
						  if ($admin->company_id === $company->id) {
								 $admin->update(['company_id' => null]);
						  }
						  
						  // Delete the company logo from storage if it exists
						  if ($company->logo
								&& Storage::disk('public')->exists(
									 $company->logo
								)
						  ) {
								 Storage::disk('public')->delete($company->logo);
						  }
						  // Delete the company
						  $company->delete();
						  
						  return responseJson(
								200,
								'Company and associated sub-admins deleted successfully'
						  );
						  
					} catch (\Exception $e) {
						  // Handle exceptions
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, $errorMessage);
					}
			 }
	  }