<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Admin;
	  use App\Models\Company;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  
	  
	  class CompanyController extends Controller
	  {
			 
			 public function index(): JsonResponse
			 {
					try {
						  // Check authenticated user
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $companies = Company::withCount('jobs')->paginate(10);
						  
						  if ($companies->isEmpty()) {
								 return responseJson(404, 'No companies found');
						  }
						  
						  return responseJson(200, 'Companies retrieved successfully', $companies);
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 public function show($id): JsonResponse
			 {
					try {
						  // Check authenticated user
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $company = Company::find($id);
						  
						  if (!$company) {
								 return responseJson(404, 'Company not found');
						  }
						  
						  // Get active jobs for this company
						  $activeJobs = $company->jobs()
								->where('company_id', $company->id)
								->activeJobs()
								->count();
						  
						  return responseJson(200, 'Company details retrieved', [
								'company' => $company,
								'active_jobs' => $activeJobs
						  ]);
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 public function store(Request $request): \Illuminate\Http\JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  
						  // Define validation rules based on user role
						  $validationRules = [];
						  $validationCustomMessages = [];
						  
						  if ($admin->hasRole('super-admin')) {
								 $validationRules = [
									  'name' => 'required|string|max:255|unique:companies',
									  'admin_id' => 'required|exists:admins,id',
									  'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
									  'description' => 'nullable|string|max:1000',
									  'location' => 'nullable|string|max:255',
									  'website' => 'nullable|url',
									  'size' => 'nullable|string|max:255',
									  'open_jobs' => 'nullable|numeric|min:0',
									  'hired_people' => 'nullable|numeric|min:0',
								 ];
						  } else {
								 if (!$admin->hasPermissionTo('manage-own-company')) {
										return responseJson(403, 'Unauthorized');
								 }
								 
								 $validationRules = [
									  'name' => 'required|string|max:255|unique:companies',
									  'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
									  'description' => 'nullable|string|max:1000',
									  'location' => 'nullable|string|max:255',
									  'website' => 'nullable|url',
									  'size' => 'nullable|string|max:255',
									  'open_jobs' => 'nullable|numeric|min:0',
									  'hired_people' => 'nullable|numeric|min:0',
								 ];
						  }
						  
						  // Add custom validation messages
						  $validationCustomMessages = [
								'name.unique' => 'A company with this name already exists.',
								'name.max' => 'Company name cannot exceed 255 characters.',
								'logo.image' => 'The logo must be an image.',
								'logo.mimes' => 'The logo must be a file of type: jpeg, png, jpg, gif, svg.',
								'logo.max' => 'The logo cannot exceed 2MB in size.',
								'description.max' => 'Company description cannot exceed 1000 characters.',
								'location.max' => 'Location cannot exceed 255 characters.',
								'size.max' => 'Company size cannot exceed 255 characters.',
								'open_jobs.min' => 'Open jobs count cannot be negative.',
								'hired_people.min' => 'Hired people count cannot be negative.',
						  ];
						  
						  // Validate request data
						  $validated = $request->validate($validationRules, $validationCustomMessages);
						  
						  // Handle logo upload
						  if ($request->hasFile('logo')) {
								 $logoPath = $request->file('logo')->store('company_logos', 'public');
								 $validated['logo'] = $logoPath;
						  }
						  
						  // Create company
						  if ($admin->hasRole('super-admin')) {
								 $company = Company::create($validated);
						  } else {
								 $validated['admin_id'] = $admin->id;
								 $company = Company::create($validated);
						  }
						  
						  // Return success response
						  return responseJson(201, 'Company created successfully', [
								'company' => $company,
								'open_jobs' => $company->open_jobs,
								'hired_people' => $company->hired_people,
						  ]);
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error: ' . $e->getMessage());
					}
			 }
			 public function update(Request $request, Company $company): \Illuminate\Http\JsonResponse
			 {
					/** @var Admin $admin */
					
					$admin = auth('admin')->user();
					
					if ($admin->hasPermissionTo('manage-all-companies')) {
						  $company->update($request->all());
					} elseif ($admin->hasPermissionTo('manage-own-company')
						 && $company->id === $admin->company_id
					) {
						  $company->update($request->all());
					} else {
						  return responseJson(403, 'Unauthorized');
					}
					
					return responseJson(200, 'Company updated', $company);
			 }
			 
			 public function destroy(Company $company): \Illuminate\Http\JsonResponse
			 {
					/** @var Admin $admin */
					
					$admin = auth('admin')->user();
					
					if ($admin->hasPermissionTo('manage-all-companies')) {
						  $company->delete();
					} elseif ($admin->hasPermissionTo('manage-own-company')
						 && $company->id === $admin->company_id
					) {
						  $company->delete();
					} else {
						  return responseJson(403, 'Unauthorized');
					}
					
					return responseJson(200, 'Company deleted');
			 }
	  }