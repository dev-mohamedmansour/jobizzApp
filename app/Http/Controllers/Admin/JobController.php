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
			 
			 public function show(Job $job)
			 {
					return responseJson(200, 'Job details', [
						 'job'          => $job->load('company', 'categories'),
						 'similar_jobs' => Job::whereHas(
							  'categories', fn($q) => $q->whereIn(
							  'id', $job->categories->pluck('id')
						 )
						 )
							  ->where('id', '!=', $job->id)
							  ->limit(5)
							  ->get()
					]);
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
			 
			 public function store(Request $request)
			 {
					try {
						  $admin = auth('admin')->user();
						  $validationRules = [];
						  $validationCustomMessages = [];
						  
						  if ($admin->hasRole('super-admin')) {
								 $validationRules = [
									  
//									  'category'     => 'required|integer|exists:categories,id',
									  'title'        => 'required|string|max:255',
									  'company_id'   => 'required|exists:companies,id',
									  'job_type'     => 'required|string|in:Full-time,Part-time,Internship,Contract',
									  'salary'       => 'required|numeric',
									  'location'     => 'required|string|max:255',
									  'description'  => 'required|string',
									  'requirements' => 'required|string',
									  'benefits'     => 'sometimes|string',
								 ];
						  } else {
								 if (!$admin->hasPermissionTo('manage-company-jobs')) {
										return responseJson(403, 'Unauthorized');
								 }
								 
								 $validationRules = [
//									  'category'     => 'required|integer|exists:categories,id',
									  'name'         => 'required|string|max:255|unique:companies',
									  'logo'         => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
									  'description'  => 'required|string|max:1000',
									  'location'     => 'required|string|max:255',
									  'website'      => 'sometimes|url',
									  'size'         => 'required|string|max:255',
									  'hired_people' => 'required|numeric|min:5',
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
			 
			 public function update(Request $request, Job $job)
			 {
					/** @var Admin $admin */
					$admin = auth('admin')->user();
					
					// Validate input
					$validated = $request->validate([
						 'title'       => 'sometimes|string|max:255',
						 'description' => 'sometimes|string',
						 'status'      => 'sometimes|in:open,close',
						 'category'    => 'sometimes|array|exists:categories,id'
					]);
					
					// Authorization check using policy
					if (!$admin || !$admin->can('update', $job)) {
						  return responseJson(403, 'Unauthorized');
					}
					
					$job->update($validated);
					if ($request->has('category')) {
						  $job->category()->sync($request->category);
					}
					return responseJson(200, 'Job updated', $job);
			 }
			 
			 public function destroy(Job $job)
			 {
					/** @var Admin $admin */
					$admin = auth('admin')->user();
					
					// Authorization check using policy
					if (!$admin || !$admin->can('delete', $job)) {
						  return responseJson(403, 'Unauthorized');
					}
					
					$job->delete();
					
					return responseJson(200, 'Job deleted');
			 }
	  }