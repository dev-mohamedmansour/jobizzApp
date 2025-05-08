<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\JobListing;
	  use App\Models\JobListing as Job;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Log;
	  
	  class JobController extends Controller
	  {
			 public function index(): JsonResponse
			 {
					try {
						  // Check if the user is authenticated
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated','Unauthenticated');
						  }
						  
						  // Determine which guard the user is authenticated with
						  if (auth()->guard('admin')->check()) {
								 $admin = auth('admin')->user();
								 if (!$admin->hasRole('admin') || !$admin->hasRole('super-admin')) {
										return responseJson(
											 403,
											 'Forbidden','You do not have permission to view this jobs'
										);
								 }
								 if ($admin->hasRole('super-admin')) {
										// Get active jobs count using a subquery
										$jobs = Job::with('company')->where('job_status','!=','cancelled')
											 ->withCount(
												  ['applications as active_applications' => function ($query
												  ) {
														 $query->where('status','!=','rejected')->where('status','!=','pending');
												  }]
											 )
											 ->get();
										
										if ($jobs->isEmpty()) {
											  return responseJson(404,'No Jobs found','No Jobs found');
										}
										
										return responseJson(
											 200, 'Jobs retrieved successfully', [
											 'jobs' => $jobs,
										]
										);
								 }else{
										return responseJson(403,'Forbidden','You do not have permission to view this jobs');
								 }
						  } elseif (auth()->guard('api')->check()) {
								 $user = auth('api')->user();
								 $profileJobTitle = $user->defaultProfile->title_job;
								 $jobsNum = Job::Where('job_status','=','open')->count();
								 $number = $jobsNum / 3;
								 // Get active jobs count using a subquery
								 $jobsTrending = Job::with('company')->where('job_status','=','open')
									  ->inRandomOrder()
									  ->take($number)
									  ->get();
								 $jobsPopular = Job::with('company')->where('job_status','=','open')
									  ->inRandomOrder()
									  ->take($number)
									  ->get();
								 $jobsRecommended = Job::with('company')->where(
									  'title', 'like', '%' . $profileJobTitle . '%'
								 )->where('job_status','=','open')
									  ->inRandomOrder()
									  ->take($number)
									  ->get();
								 if ($jobsNum == 0) {
										return responseJson(404,'No Jobs found','No Jobs found');
								 }
								 
								 return responseJson(
									  200, 'Jobs retrieved successfully', [
									  'Trending' => $jobsTrending,
									  'Popular' => $jobsPopular,
									  'Recommended' => $jobsRecommended,
								 ]
								 );
						  }
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error',
								 config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 public function getAllJobsForCompany(Request $request, $id
			 ): JsonResponse {
					try {
						  // Check if the user is authenticated
						  if (!auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated','Unauthenticated');
						  }
						  
						  // Determine which guard the user is authenticated with
						  if (auth()->guard('admin')->check()) {
								 $user = auth('admin')->user();
						  }
						  // Get active jobs count using a subquery
						  $jobs = Job::with('company')
								->withCount(
									 ['applications as active_applications' => function ($query
									 ) {
											$query->where('status','!=','rejected')->where('status','!=','pending');
									 }]
								)->whereHas('company', function ($query) use ($id) {
									  $query->where('id', $id);
								})
								->where('job_status','!=','cancelled')->get();
						  
						  if ($jobs->isEmpty()) {
								 return responseJson(404,'Error','No Jobs found');
						  }
						  
						  return responseJson(200, 'Jobs retrieved successfully', [
								'jobs' => $jobs,
						  ]);
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error',
							config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 public function show($jobId): JsonResponse
			 {
					try {
						  // Check if the user is authenticated
						  if (!auth()->check() && auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated','Unauthenticated');
						  }
						  
						  $job = Job::find($jobId)->where('job_status','!=','cancelled');
						  // Check if the job exists
						  if (!$job) {
								 return responseJson(404,'Job not found','Job not found');
						  }
						  
						  // Determine which guard the user is authenticated with
						  if (auth()->guard('admin')->check()) {
								 $user = auth('admin')->user();
								 if (!$this->isAdminAuthorizedToShow($user, $job)) {
										return responseJson(
											 403,
											 'Forbidden','You do not have permission to view this job'
										);
								 }
						  } elseif (!auth()->guard('api')->check()) {
								 // Deny access if the user is authenticated with an unknown guard
								 return responseJson(
									  403,
									  'Forbidden','You do not have permission to view this job'
								 );
						  }
						  
						  return responseJson(200, 'Job details retrieved', [
								'job'  => $job->company->jobs->find($jobId),
								'logo' => url($job->company->logo)
						  ]);
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error',
							config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 private function isAdminAuthorizedToShow($admin, $job): bool
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
					if ($admin->hasAnyRole(['hr', 'coo'])
						 && $admin->company_id === $job->company->id
					) {
						  return true;
					}
					return false;
			 }
			 public function store(Request $request): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  
						  // Check if the user is authenticated
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated','Unauthenticated');
						  }
						  
						  // Check if the admin is a super-admin (they should not create jobs directly)
						  if ($admin->hasRole('super-admin')) {
								 return responseJson(
									  403, 'Forbidden','Super-admins cannot create jobs directly'
								 );
						  }
						  
						  // Check if the admin has the permission to manage company jobs
						  if (!$admin->hasPermissionTo('manage-company-jobs')) {
								 return responseJson(403,'Forbidden', 'Unauthorized');
						  }
						  
						  // Check if the admin has a company associated
						  if (!$admin->company_id) {
								 return responseJson(
									  403,
									  'Forbidden','You can only add jobs to your own company'
								 );
						  }
						  
						  // Define validation rules
						  $validationRules = [
								'category_name' => 'required|string|exists:categories,name',
								'title'         => 'required|string|max:255|regex:/^[a-zA-Z\s,.+\-\'\/]+$/',
								'job_type'      => 'required|string|in:Full-time,Part-time,Internship,Contract',
								'salary'        => 'required|numeric|min:1000|max:100000000',
								'location'      => 'required|string|max:255|regex:/^[a-zA-Z0-9\s,.+\-]+$/u',
								'job_status'    => 'sometimes|string|in:open,closed',
								'description'   => 'required|string|max:65535|regex:/^[a-zA-Z0-9\s\']+$/',
								'requirement'   => 'required|string|max:65535|regex:/^[a-zA-Z0-9\s\']+$/',
								'benefits'      => 'sometimes|string|max:65535|regex:/^[a-zA-Z\s\']+$/',
								'position'      => 'required|string|max:100|regex:/^[a-zA-Z\s\']+$/',
						  ];
						  
						  // Add custom validation messages
						  $validationCustomMessages = [
								'category_name.required' => 'The category name field is required.',
								'category.exists'        => 'The selected category does not exist.',
								'title.required'         => 'The job title field is required.',
								'job_type.required'      => 'The job type field is required.',
								'job_type.in'            => 'The selected job type is invalid.',
								'salary.required'        => 'The salary field is required.',
								'location.required'      => 'The location field is required.',
								'description.required'   => 'The description field is required.',
								'requirement.required'   => 'The requirement field is required.',
						  ];
						  
						  // Validate request data
						  $validated = $request->validate(
								$validationRules, $validationCustomMessages
						  );
						  
						  // Check if a job with the same title and position already exists for the company
						  $existingJob = Job::where('company_id', $admin->company_id)
								->where('title', $validated['title'])
								->where('position', $validated['position'])
								->first();
						  
						  if ($existingJob) {
								 return responseJson(409, 'Job already exists','Job already exists');
						  }
						  // Create the job with the admin's company_id
						  $jobData = $validated;
						  $jobData['company_id'] = $admin->company_id;
						  
						  $job = Job::create($jobData);
						  
						  return responseJson(201, 'Job created successfully', [
								'job'  => $job,
								'logo' => $admin->company->logo,
						  ]);
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
						  return responseJson(
								422,
								" validation error",
								$e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  // Log the error
						  Log::error('Server Error: ' . $e->getMessage());
						  // Return a generic error message in production
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, 'Server Error',$errorMessage);
					}
			 }
			 public function update(Request $request, $jobId): JsonResponse
			 {
					try {
						  // Check if the user is authenticated
						  $admin = auth('admin')->user();
						  
						  // Check if the user is authenticated
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated','Unauthenticated');
						  }
						  
						  // Check if the admin is a super-admin (they should not create jobs directly)
						  if ($admin->hasRole('super-admin')) {
								 return responseJson(
									  403, 'Forbidden','Super-admins can not update jobs directly'
								 );
						  }
						  
						  // Check if the admin has the permission to manage company jobs
						  if (!$admin->hasPermissionTo('manage-company-jobs')) {
								 return responseJson(403,'Forbidden','You do not have permission to manage company jobs');
						  }
						  
						  // Check if the admin has a company associated
						  if (!$admin->company_id) {
								 return responseJson(
									  403,
									  'Forbidden','You can only add jobs to your own company'
								 );
						  }
						  $job = Job::find($jobId)->where('job_status','!=','cancelled')->where('company_id',$admin->company_id);
						  // Check if the job exists
						  if (!$job) {
								 return responseJson(404,'Job not found','Job not found');
						  }
						  
						  if ($admin->company_id !== $job->company_id) {
								 return responseJson(
									  403,
									  'Forbidden','You can only update jobs from your own company'
								 );
						  }
						  
						  // Define validation rules
						  $validationRules = [
								'category_name' => 'sometimes|string|exists:categories,name',
								'title'         => 'sometimes|string|max:255|regex:/^[a-zA-Z\s,.+\-\'\/]+$/',
								'job_type'      => 'sometimes|string|in:Full-time,Part-time,Internship,Contract',
								'salary'        => 'sometimes|numeric|min:1000|max:100000000',
								'location'      => 'sometimes|string|max:255|regex:/^[a-zA-Z0-9\s,.+\-]+$/u',
								'job_status'    => 'sometimes|string|in:open,closed',
								'description'   => 'sometimes|string|max:65535|regex:/^[a-zA-Z0-9\s\']+$/',
								'requirement'   => 'sometimes|string|max:65535|regex:/^[a-zA-Z0-9\s\']+$/',
								'benefits'      => 'sometimes|string|max:65535|regex:/^[a-zA-Z\s\']+$/',
								'position'      => 'sometimes|string|max:100|regex:/^[a-zA-Z\s\']+$/',
						  ];
						  
						  // Add custom validation messages
						  $validationCustomMessages = [
								'category.exists' => 'The selected category does not exist.',
								'job_type.in'     => 'The selected job type is invalid.',
						  ];
						  
						  
						  // Validate request data
						  $validated = $request->validate(
								$validationRules, $validationCustomMessages
						  );
						  
						  // Update the job
						  $job->update($validated);
						  
						  // Return success response
						  return responseJson(200, 'Job updated successfully', [
								'job'  => $job->company->jobs->find($jobId),
								'logo' => $job->company->logo,
						  ]);
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
						  return responseJson(
								422,
								"Validation error",
								$e->validator->errors()->all()
						  );
					} catch
					(\Exception $e) {
						  // Handle other exceptions
						  Log::error('Server Error: ' . $e->getMessage());
						  // For production: Generic error message
						  $errorMessage
								= "Server error: Something went wrong. Please try again later.";
						  // For development: Detailed error message
						  if (config('app.debug')) {
								 $errorMessage = "Server error: " . $e->getMessage();
						  }
						  return responseJson(500, 'Server Error',$errorMessage);
					}
			 }
			 public function destroy($jobId): JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated', 'Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  
						  // Find the job (using JobListing as per your model)
						  $job = JobListing::find($jobId);
						  if (!$job) {
								 return responseJson(404, 'Job not found', 'Job not found');
						  }elseif($job->job_status === 'cancelled') {
								 return responseJson(400, 'Job already cancelled', 'This job has already been cancelled.');
						  }
						  
						  // Check authorization
						  if (!$this->isAuthorizedToDelete($admin, $job)) {
								 return responseJson(403, 'Forbidden', 'You do not have permission to delete this job');
						  }
						  
						  // Set job status to cancel
						  $job->job_status = 'cancelled';
						  $job->save();
						  
						  // Update all related applications to reject
						  $job->applications->each(function ($application) {
								 $application->statuses()->create([
									  'status' => 'rejected',
									  'feedback' => 'Job was cancelled by admin.',
								 ]);
						  });
						  
						  // Schedule deletion of job and related applications after 15 days
						  \Illuminate\Support\Facades\Artisan::call('schedule:run'); // Ensure scheduler is running
						  \Illuminate\Support\Facades\Log::info("Scheduling deletion for job ID: {$jobId} after 15 days");
						  
						  // Schedule the deletion using a queued job
						  \App\Jobs\DeleteJobAndApplications::dispatch($jobId)
								->delay(now()->addDays(15));
						  
						  return responseJson(200, 'Job marked as cancelled and applications rejected. Scheduled for deletion in 15 days.');
						  
					} catch (\Exception $e) {
						  // Handle exceptions
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, 'Server Error', $errorMessage);
					}
			 }
			 
			 private function isAuthorizedToDelete($admin, $job): bool
			 {
					return ($admin->hasPermissionTo('manage-company-jobs')
							  && $admin->company_id === $job->company_id);
			 }
	  }