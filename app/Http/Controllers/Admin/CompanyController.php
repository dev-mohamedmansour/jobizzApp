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
						  
						  $company = Company::find($id);
						  
						  if (!$company) {
								 return responseJson(404, 'Company not found');
						  }
						  
						  // Determine which guard the user is authenticated with
						  if (auth()->guard('admin')->check()) {
								 $user = auth('admin')->user();
								 if (!$this->isAdminAuthorized($user, $company)) {
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
									  'size'         => 'required|string|max:255',
									  // Make size required
									  'hired_people' => 'required|numeric|min:5',
								 ];
						  } else {
								 if (!$admin->hasPermissionTo('manage-own-company')) {
										return responseJson(403, 'Unauthorized');
								 }
								 
								 // Check if admin already has a company
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
									  'size'         => 'required|string|max:255',
									  // Make size required
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
								 $validated['logo'] = $logoPath;
						  } else {
								 // Set default image URL
								 $validated['logo']
									  = 'https://jobizaa.com/still_images/companyLogoDefault.jpeg';
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
//								 $targetAdmin->update(['company_id' => $company->id]);
						  } else {
								 $validated['admin_id'] = $admin->id;
								 $company = Company::create($validated);
//								 $admin->update([
//									  'company_id' => $company->id
//								 ]);
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
									  'hired_people' => 'sometimes|numeric|min:5',
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
									  'hired_people' => 'sometimes|numeric|min:5',
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
								 $validated['logo'] = $logoPath;
						  } elseif (isset($validated['logo'])
								&& $validated['logo'] === ''
						  ) {
								 // If the logo is empty, remove the existing logo
								 if ($company->logo
									  && Storage::disk('public')->exists(
											$company->logo
									  )
								 ) {
										Storage::disk('public')->delete($company->logo);
								 }
								 $validated['logo']
									  = 'https://jobizaa.com/still_images/companyLogoDefault.jpeg';
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
										} elseif ($targetAdmin->company->id
											 !== $company->id
										) {
											  return responseJson(
													400,
													'The specified admin not has a company '
											  );
										}
										$company->update($validated);
								 } else {
										return responseJson(
											 404, 'This admin not found'
										);
								 }
						  } else {
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
								"Validation error",
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
			 
//			 public function destroy(Company $company
//			 ): \Illuminate\Http\JsonResponse {
//					/** @var Admin $admin */
//
//					$admin = auth('admin')->user();
//
//					if ($admin->hasPermissionTo('manage-all-companies')) {
//						  $company->delete();
//					} elseif ($admin->hasPermissionTo('manage-own-company')
//						 && $company->id === $admin->company_id
//					) {
//						  $company->delete();
//					} else {
//						  return responseJson(403, 'Unauthorized');
//					}
//
//					return responseJson(200, 'Company deleted');
//			 }
			 public function destroy(Company $company): JsonResponse
			 {
					try {
						  // Check if the user is authenticated
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  
						  // Check if the company exists
						  if (!$company) {
								 return responseJson(404, 'Company not found');
						  }
						  
						  // Determine authorization
						  if ($admin->hasPermissionTo('manage-all-companies')) {
								 // Super-admins can delete any company
						  } elseif ($admin->hasPermissionTo('manage-own-company') && $company->id === $admin->company_id) {
								 // Regular admins can only delete their own company
						  } else {
								 return responseJson(403, 'Forbidden: You do not have permission to delete this company');
						  }
						  
						  // Delete associated resources if needed (e.g., jobs, admins)
						  $company->jobs()->delete();
						  $company->admins()->delete();
						  
						  // Delete the company
						  $company->delete();
						  
						  return responseJson(200, 'Company deleted successfully');
						  
					} catch (\Exception $e) {
						  // Handle exceptions
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage() : 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, $errorMessage);
					}
			 }
	  }