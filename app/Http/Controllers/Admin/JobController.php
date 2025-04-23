<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Admin;
	  use App\Models\JobListing as Job;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Log;
	  
	  class JobController extends Controller
	  {
			 public function index(Request $request): JsonResponse
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
											 'Forbidden: You do not have permission to view this jobs'
										);
								 }
						  } elseif (!auth()->guard('api')->check()) {
								 // Deny access if the user is authenticated with an unknown guard
								 return responseJson(
									  403,
									  'Forbidden: You do not have permission to view this jobs'
								 );
						  }
						  $jobs = Job::with(
								'company'
						  ) // Eager load the company relationship
						  ->withCount('scopeActiveJobs')
								->paginate(10);
						  
						  if ($jobs->isEmpty()) {
								 return responseJson(404, 'No Jobs found');
						  }
						  
						  return responseJson(
								200,
								'Jobs retrieved successfully',
								$jobs->map(function ($job) {
									  return [
											'job'  => $job,
											'logo' => optional($job->company)->logo,
											// Get company logo if company exists
									  ];
								})
						  );
//						  $jobs = Job::withCount('scopeActiveJobs')->paginate(10);
//
//						  if ($jobs->isEmpty()) {
//								 return responseJson(404, 'No Jobs found');
//						  }
//
//						  return responseJson(
//								200, 'Jobs retrieved successfully', $jobs
//						  );
					
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
					/***/
//					$jobs = Job::with('company', 'categories')
//						 ->when($request->category, function ($query) use ($request) {
//								$query->whereHas(
//									 'categories',
//									 fn($q) => $q->where('slug', $request->category)
//								);
//						 })
//						 ->paginate(15);
//
//					return responseJson(200, 'Jobs retrieved', $jobs);
			 }
			 
			 private function isAdminAuthorized($admin): bool
			 {
					// Check if the user is a super-admin
					if ($admin->hasRole('super-admin')) {
						  return true;
					}
					return false;
			 }
			 
//			 public function show2(Job $job)
//			 {
//					return responseJson(200, 'Job details', [
//						 'job'          => $job->load('company', 'categories'),
//						 'similar_jobs' => Job::whereHas(
//							  'categories', fn($q) => $q->whereIn(
//							  'id', $job->categories->pluck('id')
//						 )
//						 )
//							  ->where('id', '!=', $job->id)
//							  ->limit(5)
//							  ->get()
//					]);
//			 }
			 
			 private function isAdminAuthorizedToShow($admin,$job): bool
			 {
					// Check if the user is a super-admin
					if ($admin->hasRole('super-admin')) {
						  return true;
					}
					// Check if the user is the admin who created the company
					if ($admin->id === $job->company->admin_id) {
						  return true;
					}
					// Check if the user is an HR or COO associated with the company
					if ($admin->hasAnyRole(['hr', 'coo']) && $admin->company_id === $job->company->id) {
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
						  
						  $job = Job::find($id);
						  
						  if (!$job) {
								 return responseJson(404, 'Job not found');
						  }
						  
						  // Determine which guard the user is authenticated with
						  if (auth()->guard('admin')->check()) {
								 $user = auth('admin')->user();
								 if (!$this->isAdminAuthorizedToShow($user, $job)) {
										return responseJson(
											 403,
											 'Forbidden: You do not have permission to view this job'
										);
								 }
						  } elseif (!auth()->guard('api')->check()) {
								 // Deny access if the user is authenticated with an unknown guard
								 return responseJson(
									  403,
									  'Forbidden: You do not have permission to view this job'
								 );
						  }

						  return responseJson(200, 'Company details retrieved', [
								'job'     => $job,
								'logo' => optional($job->company)->logo,
						  ]);
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
//			 public function index()
//			 {
//					/** @var Admin $admin */
//
//					$admin = auth('admin')->user();
//
//					// Check if admin is authenticated
//					if (!$admin) {
//						  return responseJson(401, 'Unauthenticated');
//					}
//
//					// Check permissions using the admin instance
//					if ($admin->hasPermissionTo('manage-all-jobs')) {
//						  $jobs = Job::all();
//					} elseif ($admin->hasPermissionTo('manage-company-jobs')) {
//						  $jobs = Job::where('company_id', $admin->company_id)->get();
//					} else {
//						  return responseJson(403, 'Unauthorized');
//					}
//
//					return responseJson(200, 'Jobs retrieved', $jobs);
//			 }
			 
			 public function store(Request $request):JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  $validationRules = [];
						  $validationCustomMessages = [];
						  
						  if ($admin->hasRole('super-admin')) {
								 $validationRules = [
									  'category' => [
											'required',
											'integer',
											'exists:categories,id',
									  ],
									  'title' => [
											'required',
											'string',
											'max:255',
									  ],
									  'company_id' => [
											'required',
											'exists:companies,id',
									  ],
									  'job_type' => [
											'required',
											'string',
											'in:Full-time,Part-time,Internship,Contract',
									  ],
									  'salary' => [
											'required',
											'numeric',
											'min:1000', // Ensure salary is at least 1000
											'max:100000000', // Ensure salary is at most 100,000,000
									  ],
									  'location' => [
											'required',
											'string',
											'max:255',
											'regex:/^[a-zA-Z0-9\s,.+-]+$/u', // Allow letters, numbers, spaces, and common special characters
									  ],
									  'description' => [
											'required',
											'string',
											'max:65535', // Allow longer descriptions
									  ],
									  'requirements' => [
											'required',
											'string',
											'max:65535', // Allow longer requirements
									  ],
									  'benefits' => [
											'sometimes',
											'string',
											'max:65535', // Allow longer benefits descriptions
									  ],
									  // Add more advanced validation rules as needed
								 ];
						  } else {
								 if (!$admin->hasPermissionTo('manage-company-jobs')) {
										return responseJson(403, 'Unauthorized');
								 }
								 
								 $validationRules = [
									  'category' => [
											'required',
											'integer',
											'exists:categories,id',
									  ],
									  'title' => [
											'required',
											'string',
											'max:255',
									  ],
									  'job_type' => [
											'required',
											'string',
											'in:Full-time,Part-time,Internship,Contract',
									  ],
									  'salary' => [
											'required',
											'numeric',
											'min:1000', // Ensure salary is at least 1000
											'max:100000000', // Ensure salary is at most 100,000,000
									  ],
									  'location' => [
											'required',
											'string',
											'max:255',
											'regex:/^[a-zA-Z0-9\s,.+-]+$/u', // Allow letters, numbers, spaces, and common special characters
									  ],
									  'description' => [
											'required',
											'string',
											'max:65535', // Allow longer descriptions
									  ],
									  'requirements' => [
											'required',
											'string',
											'max:65535', // Allow longer requirements
									  ],
									  'benefits' => [
											'sometimes',
											'string',
											'max:65535', // Allow longer benefits descriptions
									  ],
									  // Add more advanced validation rules as needed
								 ];
								 
								 if (!$admin->company_id) {
										return responseJson(
											 403,
											 'Forbidden: You can only add jobs to your own company'
										);
								 }
								 
								 $validationRules['company_id'] = $admin->company_id;
						  }
						  
						  // Add custom validation messages
						  $validationCustomMessages = [
								'category.required' => 'The category field is required.',
								'category.exists' => 'The selected category does not exist.',
								'title.required' => 'The job title field is required.',
								'job_type.required' => 'The job type field is required.',
								'job_type.in' => 'The selected job type is invalid.',
								'salary.required' => 'The salary field is required.',
								'location.required' => 'The location field is required.',
								'description.required' => 'The description field is required.',
								'requirements.required' => 'The requirements field is required.',
						  ];
						  
						  // Validate request data
						  $validated = $request->validate(
								$validationRules, $validationCustomMessages
						  );
						  
						  $job = Job::create($validated);
						  
						  // Return success response
						  return responseJson(201, 'Job created successfully', [
								'job'  => $job,
								'logo' => $job->company->logo,
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
			 
			 public function update(Request $request, Job $job): JsonResponse
			 {
					try {
						  // Check if the user is authenticated
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  
						  // Check if the job exists
						  if (!$job) {
								 return responseJson(404, 'Job not found');
						  }
						  
						  // Define validation rules based on user role
						  $validationRules = [];
						  
						  if ($admin->hasRole('super-admin')) {
								 $validationRules = [
//									  'category' => 'sometimes|integer|exists:categories,id',
									  'title' => 'sometimes|string|max:255',
									  'company_id' => 'sometimes|exists:companies,id',
									  'job_type' => 'sometimes|string|in:Full-time,Part-time,Internship,Contract',
									  'salary' => 'sometimes|numeric|min:1000|max:100000000',
									  'location' => 'sometimes|string|max:255|regex:/^[a-zA-Z0-9\s,.+-]+$/u',
									  'description' => 'sometimes|string|max:65535',
									  'requirements' => 'sometimes|string|max:65535',
									  'benefits' => 'sometimes|string|max:65535',
								 ];
						  } else {
								 if (!$admin->hasPermissionTo('manage-company-jobs')) {
										return responseJson(403, 'Unauthorized');
								 }
								 
								 if ($admin->company_id !== $job->company_id) {
										return responseJson(403, 'Forbidden: You can only update jobs from your own company');
								 }
								 
								 $validationRules = [
//									  'category' => 'sometimes|integer|exists:categories,id',
									  'title' => 'sometimes|string|max:255',
									  'job_type' => 'sometimes|string|in:Full-time,Part-time,Internship,Contract',
									  'salary' => 'sometimes|numeric|min:1000|max:100000000',
									  'location' => 'sometimes|string|max:255|regex:/^[a-zA-Z0-9\s,.+-]+$/u',
									  'description' => 'sometimes|string|max:65535',
									  'requirements' => 'sometimes|string|max:65535',
									  'benefits' => 'sometimes|string|max:65535',
								 ];
						  }
						  
						  // Add custom validation messages
						  $validationCustomMessages = [
//								'category.exists' => 'The selected category does not exist.',
								'job_type.in' => 'The selected job type is invalid.',
								'salary.min' => 'The salary must be at least 1000.',
								'salary.max' => 'The salary must be less than 100,000,000.',
						  ];
						  
						  // Validate request data
						  $validated = $request->validate($validationRules, $validationCustomMessages);
						  
						  // Update the job
						  $job->update($validated);
						  
						  // Return success response
						  return responseJson(200, 'Job updated successfully', [
								'job'  => $job,
								'logo' => $job->company->logo,
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
						  $errorMessage = "Server error: Something went wrong. Please try again later.";
						  // For development: Detailed error message
						  if (config('app.debug')) {
								 $errorMessage = "Server error: " . $e->getMessage();
						  }
						  return responseJson(500, $errorMessage);
					}
			 }
			 public function destroy(Job $job): JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  
						  // Check if the job exists
						  if (!$job) {
								 return responseJson(404, 'Job not found');
						  }
						  
						  // Check authorization
						  if (!$this->isAuthorizedToDelete($admin, $job)) {
								 return responseJson(403, 'Forbidden: You do not have permission to delete this job');
						  }
						  
						  // Delete the job
						  $job->delete();
						  
						  return responseJson(200, 'Job deleted successfully');
						  
					} catch (\Exception $e) {
						  // Handle exceptions
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage() : 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 /**
			  * Check if the admin is authorized to delete the job.
			  */
			 private function isAuthorizedToDelete($admin, $job): bool
			 {
					return $admin->hasRole('super-admin') ||
						 ($admin->hasPermissionTo('manage-company-jobs') && $admin->company_id === $job->company_id);
			 }
	  }