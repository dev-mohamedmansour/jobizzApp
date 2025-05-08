<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\JobListing;
	  use App\Models\User;
	  use App\Notifications\JobizzUserNotification;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Cache;
	  use Illuminate\Support\Facades\Log;
	  
	  class JobController extends Controller
	  {
			 /**
			  * Retrieve a list of jobs based on the authenticated user's role and guard.
			  *
			  * @return JsonResponse
			  */
			 public function index(): JsonResponse
			 {
					try {
						  if (!auth()->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  if (auth()->guard('admin')->check()) {
								 return $this->handleAdminJobs();
						  }
						  
						  if (auth()->guard('api')->check()) {
								 return $this->handleApiUserJobs();
						  }
						  
						  return responseJson(
								403, 'Forbidden', 'Invalid authentication guard'
						  );
					} catch (\Exception $e) {
						  Log::error('Index error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Handle job retrieval for admin users.
			  *
			  * @return JsonResponse
			  */
			 private function handleAdminJobs(): JsonResponse
			 {
					$admin = auth('admin')->user();
					
					if (!$admin->hasRole(['admin', 'super-admin'])) {
						  return responseJson(
								403, 'Forbidden',
								'You do not have permission to view these jobs'
						  );
					}
					
					if ($admin->hasRole('super-admin')) {
						  $jobs = JobListing::with('company')
								->where('job_status', '!=', 'cancelled')
								->withCount([
									 'applications as active_applications' => fn($query
									 ) => $query
										  ->where('status', '!=', 'rejected')
										  ->where('status', '!=', 'pending'),
								])
								->get();
						  
						  if ($jobs->isEmpty()) {
								 return responseJson(
									  404, 'No Jobs found', 'No Jobs found'
								 );
						  }
						  
						  return responseJson(
								200, 'Jobs retrieved successfully', ['jobs' => $jobs]
						  );
					}
					
					return responseJson(
						 403, 'Forbidden',
						 'You do not have permission to view these jobs'
					);
			 }
			 
			 /**
			  * Handle job retrieval for API users.
			  *
			  * @return JsonResponse
			  */
			 private function handleApiUserJobs(): JsonResponse
			 {
					$user = auth('api')->user();
					$profileJobTitle = $user->defaultProfile->title_job;
					$totalJobsCount = Cache::remember(
						 'open_jobs_count', now()->addMinutes(30),
						 fn() => JobListing::where('job_status', 'open')->count()
					);
					
					if ($totalJobsCount === 0) {
						  return responseJson(404, 'No Jobs found', 'No Jobs found');
					}
					
					$jobsPerCategory = (int)($totalJobsCount / 3);
					
					$jobsTrending = Cache::remember(
						 'trending_jobs', now()->addHours(1),
						 fn() => JobListing::with('company')
							  ->where('job_status', 'open')
							  ->inRandomOrder()
							  ->take($jobsPerCategory)
							  ->get()
					);
					
					$jobsPopular = Cache::remember(
						 'popular_jobs', now()->addHours(1),
						 fn() => JobListing::with('company')
							  ->where('job_status', 'open')
							  ->inRandomOrder()
							  ->take($jobsPerCategory)
							  ->get()
					);
					
					$jobsRecommended = JobListing::with('company')
						 ->where('title', 'like', '%' . $profileJobTitle . '%')
						 ->where('job_status', 'open')
						 ->inRandomOrder()
						 ->take($jobsPerCategory)
						 ->get();
					
					return responseJson(200, 'Jobs retrieved successfully', [
						 'Trending'    => $jobsTrending,
						 'Popular'     => $jobsPopular,
						 'Recommended' => $jobsRecommended,
					]);
			 }
			 
			 /**
			  * Retrieve jobs for a specific company.
			  *
			  * @param Request $request
			  * @param int     $companyId
			  *
			  * @return JsonResponse
			  */
			 public function getAllJobsForCompany(Request $request, int $companyId
			 ): JsonResponse {
					try {
						  if (!auth('admin')->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $admin = auth('admin')->user();
						  
						  $jobs = JobListing::with('company')
								->where('job_status', '!=', 'cancelled')
								->whereHas(
									 'company',
									 fn($query) => $query->where('id', $companyId)
								)
								->withCount([
									 'applications as active_applications' => fn($query
									 ) => $query
										  ->where('status', '!=', 'rejected')
										  ->where('status', '!=', 'pending'),
								])
								->get();
						  
						  if ($jobs->isEmpty()) {
								 return responseJson(
									  404, 'No Jobs found', 'No Jobs found'
								 );
						  }
						  
						  return responseJson(
								200, 'Jobs retrieved successfully', ['jobs' => $jobs]
						  );
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(
								404, 'Resource not found', 'Company not found'
						  );
					} catch (\Exception $e) {
						  Log::error(
								'GetAllJobsForCompany error: ' . $e->getMessage()
						  );
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Retrieve details of a specific job.
			  *
			  * @param int $jobId
			  *
			  * @return JsonResponse
			  */
			 public function show(int $jobId): JsonResponse
			 {
					try {
						  if (!auth()->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $job = JobListing::with('company')
								->where('job_status', '!=', 'cancelled')
								->find($jobId);
						  
						  if (!$job) {
								 return responseJson(
									  404, 'Job not found', 'Job not found'
								 );
						  }
						  
						  if (auth()->guard('admin')->check()) {
								 $admin = auth('admin')->user();
								 if (!$this->isAdminAuthorizedToShow($admin, $job)) {
										return responseJson(
											 403, 'Forbidden',
											 'You do not have permission to view this job'
										);
								 }
						  } elseif (!auth()->guard('api')->check()) {
								 return responseJson(
									  403, 'Forbidden',
									  'You do not have permission to view this job'
								 );
						  }
						  
						  return responseJson(200, 'Job details retrieved', [
								'job'  => $job,
								'logo' => url($job->company->logo),
						  ]);
					} catch (\Exception $e) {
						  Log::error('Show error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Check if an admin is authorized to view a job.
			  *
			  * @param mixed      $admin
			  * @param JobListing $job
			  *
			  * @return bool
			  */
			 private function isAdminAuthorizedToShow(mixed $admin, JobListing $job
			 ): bool {
					if ($admin->hasRole('super-admin')) {
						  return true;
					}
					
					if ($admin->id === $job->company->admin_id) {
						  return true;
					}
					
					if ($admin->hasAnyRole(['hr', 'coo'])
						 && $admin->company_id === $job->company->id
					) {
						  return true;
					}
					
					return false;
			 }
			 
			 /**
			  * Create a new job listing.
			  *
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function store(Request $request): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  
						  if (!$admin) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  if ($admin->hasRole('super-admin')) {
								 return responseJson(
									  403, 'Forbidden',
									  'Super-admins cannot create jobs directly'
								 );
						  }
						  
						  if (!$admin->hasPermissionTo('manage-company-jobs')) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  if (!$admin->company_id) {
								 return responseJson(
									  403, 'Forbidden',
									  'You can only add jobs to your own company'
								 );
						  }
						  
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
						  
						  $validated = $request->validate($validationRules);
						  
						  $existingJob = JobListing::where(
								'company_id', $admin->company_id
						  )
								->where('title', $validated['title'])
								->where('position', $validated['position'])
								->first();
						  
						  if ($existingJob) {
								 return responseJson(
									  409, 'Job already exists', 'Job already exists'
								 );
						  }
						  
						  $job = JobListing::create(
								array_merge(
									 $validated, ['company_id' => $admin->company_id]
								)
						  );
						  
						  User::all()->each(function ($user) use ($job) {
								 $user->notify(
									  new JobizzUserNotification(
											title: 'New Job Posted',
											body: "A new job, {$job->title}, is available at {$job->company->name}.",
											data: ['job_title' => $job->title]
									  )
								 );
						  });
						  
						  return responseJson(201, 'Job created successfully', [
								'job'  => $job,
								'logo' => $admin->company->logo,
						  ]);
					} catch (\Illuminate\Validation\ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(
								404, 'Resource not found',
								'Category or company not found'
						  );
					} catch (\Exception $e) {
						  Log::error('Store error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Mark a job as canceled and schedule its deletion.
			  *
			  * @param int $jobId
			  *
			  * @return JsonResponse
			  */
			 public function destroy(int $jobId): JsonResponse
			 {
					try {
						  if (!auth('admin')->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $admin = auth('admin')->user();
						  $job = JobListing::where('company_id', $admin->company_id)
								->find($jobId);
						  
						  if (!$job) {
								 return responseJson(
									  404, 'Job not found', 'Job not found'
								 );
						  }
						  
						  if ($job->job_status === 'cancelled') {
								 return responseJson(
									  400, 'Job already cancelled',
									  'This job has already been cancelled'
								 );
						  }
						  
						  if (!$this->isAuthorizedToDelete($admin, $job)) {
								 return responseJson(
									  403, 'Forbidden',
									  'You do not have permission to delete this job'
								 );
						  }
						  
						  $job->job_status = 'cancelled';
						  $job->save();
						  
						  $job->applications->each(function ($application) {
								 $application->update(['status' => 'rejected']);
								 $application->statuses()->create([
									  'status'   => 'rejected',
									  'feedback' => 'Job was cancelled by admin.',
								 ]);
						  });
						  
						  \App\Jobs\DeleteJobAndApplications::dispatch($jobId)->delay(
								now()->addDays(15)
						  );
						  
						  Log::info(
								"Scheduled deletion for job ID: {$jobId} after 15 days"
						  );
						  
						  return responseJson(
								200,
								'Job marked as cancelled and applications rejected. Scheduled for deletion in 15 days'
						  );
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(
								404, 'Resource not found', 'Job not found'
						  );
					} catch (\Exception $e) {
						  Log::error('Destroy error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Check if an admin is authorized to delete a job.
			  *
			  * @param mixed      $admin
			  * @param JobListing $job
			  *
			  * @return bool
			  */
			 private function isAuthorizedToDelete(mixed $admin, JobListing $job): bool
			 {
					return $admin->hasPermissionTo('manage-company-jobs')
						 && $admin->company_id === $job->company_id;
			 }
			 
			 /**
			  * Update an existing job listing.
			  *
			  * @param Request $request
			  * @param int     $jobId
			  *
			  * @return JsonResponse
			  */
			 public function update(Request $request, int $jobId): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  
						  if (!$admin) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  if ($admin->hasRole('super-admin')) {
								 return responseJson(
									  403, 'Forbidden',
									  'Super-admins cannot update jobs directly'
								 );
						  }
						  
						  if (!$admin->hasPermissionTo('manage-company-jobs')) {
								 return responseJson(
									  403, 'Forbidden',
									  'You do not have permission to manage company jobs'
								 );
						  }
						  
						  if (!$admin->company_id) {
								 return responseJson(
									  403, 'Forbidden',
									  'You can only update jobs from your own company'
								 );
						  }
						  
						  $job = JobListing::where('job_status', '!=', 'cancelled')
								->where('company_id', $admin->company_id)
								->find($jobId);
						  
						  if (!$job) {
								 return responseJson(
									  404, 'Job not found', 'Job not found'
								 );
						  }
						  
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
						  
						  $validated = $request->validate($validationRules);
						  
						  $job->update($validated);
						  
						  return responseJson(200, 'Job updated successfully', [
								'job'  => $job->fresh(['company']),
								'logo' => $job->company->logo,
						  ]);
					} catch (\Illuminate\Validation\ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(
								404, 'Resource not found', 'Job or company not found'
						  );
					} catch (\Exception $e) {
						  Log::error('Update error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
	  }