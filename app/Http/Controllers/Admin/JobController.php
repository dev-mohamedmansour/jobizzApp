<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Jobs\DeleteJobAndApplications;
	  use App\Models\JobListing;
	  use App\Models\User;
	  use App\Notifications\JobizzUserNotification;
	  use Illuminate\Database\Eloquent\ModelNotFoundException;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Cache;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Validation\ValidationException;
	  
	  class JobController extends Controller
	  {
			 /**
			  * Retrieve a list of jobs based on the authenticated user's role and guard.
			  */
			 public function index(): JsonResponse
			 {
					try {
						  if (auth('admin')->check()) {
								 return $this->handleAdminJobs();
						  }
						  
						  if (auth('api')->check()) {
								 return $this->handleApiUserJobs();
						  }
						  
						  return responseJson(403, 'Forbidden', 'Invalid authentication guard');
					} catch (\Exception $e) {
						  Log::error('Index jobs error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve jobs');
					}
			 }
			 
			 /**
			  * Handle job retrieval for admin users with pagination.
			  */
			 private function handleAdminJobs(): JsonResponse
			 {
					$admin = auth('admin')->user();
					if (!$this->isAdminAuthorized($admin)) {
						  return responseJson(403, 'Forbidden', 'You do not have permission to view these jobs');
					}
					
					try {
						  $page = request()->get('page', 1);
						  $cacheKey = "admin_jobs_page_{$page}";
						  $jobs = Cache::store('redis')->remember($cacheKey, now()->addMinutes(15), fn() =>
						  JobListing::query()
								->when(!$admin->hasRole('super-admin'), fn($query) =>
								$query->where('company_id', $admin->company_id))
								->where('job_status', '!=', 'cancelled')
								->with(['company' => fn($query) => $query->select('id', 'name', 'logo')])
								->withCount([
									 'applications as active_applications' => fn($query) =>
									 $query->whereNotIn('status', ['rejected', 'pending']),
								])
								->paginate(10)
								->through(fn($job) => $this->mapJobData($job))
						  );
						  
						  if ($jobs->isEmpty()) {
								 return responseJson(404, 'No jobs found');
						  }
						  
						  return responseJson(200, 'Jobs retrieved successfully', $jobs);
					} catch (\Exception $e) {
						  Log::error('Handle admin jobs error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve jobs');
					}
			 }
			 
			 /**
			  * Handle job retrieval for API users with trending, popular, and recommended categories.
			  */
			 private function handleApiUserJobs(): JsonResponse
			 {
					try {
						  $user = auth('api')->user();
						  $profileJobTitle = $user->defaultProfile->title_job;
						  $totalJobsCount = Cache::store('redis')->remember('open_jobs_count', now()->addMinutes(30), fn() =>
						  JobListing::where('job_status', 'open')->count()
						  );
						  
						  if ($totalJobsCount === 0) {
								 return responseJson(404, 'No jobs found');
						  }
						  
						  $jobsPerCategory = max(1, (int)($totalJobsCount / 3));
						  $cacheKeyTrending = 'trending_jobs';
						  $cacheKeyPopular = 'popular_jobs';
						  
						  $jobsTrending = Cache::store('redis')->remember($cacheKeyTrending, now()->addHours(1), fn() =>
						  JobListing::with(['company' => fn($query) => $query->select('id', 'name', 'logo')])
								->where('job_status', 'open')
								->inRandomOrder()
								->take($jobsPerCategory)
								->get()
								->map(fn($job) => $this->mapJobData($job))
						  );
						  
						  $jobsPopular = Cache::store('redis')->remember($cacheKeyPopular, now()->addHours(1), fn() =>
						  JobListing::with(['company' => fn($query) => $query->select('id', 'name', 'logo')])
								->where('job_status', 'open')
								->inRandomOrder()
								->take($jobsPerCategory)
								->get()
								->map(fn($job) => $this->mapJobData($job))
						  );
						  
						  $jobsRecommended = Cache::store('redis')->remember(
								"recommended_jobs_{$user->id}",
								now()->addHours(1),
								fn() => JobListing::with(['company' => fn($query) => $query->select('id', 'name', 'logo')])
									 ->where('title', 'like', '%' . $profileJobTitle . '%')
									 ->where('job_status', 'open')
									 ->inRandomOrder()
									 ->take($jobsPerCategory)
									 ->get()
									 ->map(fn($job) => $this->mapJobData($job))
						  );
						  
						  return responseJson(200, 'Jobs retrieved successfully', [
								'Trending' => $jobsTrending,
								'Popular' => $jobsPopular,
								'Recommended' => $jobsRecommended,
						  ]);
					} catch (\Exception $e) {
						  Log::error('Handle API user jobs error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve jobs');
					}
			 }
			 
			 /**
			  * Map job data for consistent response format.
			  */
			 private function mapJobData(JobListing $job): array
			 {
					return [
						 'id' => $job->id,
						 'title' => $job->title,
						 'company' => [
							  'id' => $job->company->id,
							  'name' => $job->company->name,
							  'logo' => $job->company->logo,
						 ],
						 'category_name' => $job->category_name,
						 'job_type' => $job->job_type,
						 'salary' => $job->salary,
						 'location' => $job->location,
						 'job_status' => $job->job_status,
						 'description' => $job->description,
						 'requirement' => $job->requirement,
						 'benefits' => $job->benefits,
						 'position' => $job->position,
						 'created_at' => $job->created_at->toDateTimeString(),
						 'updated_at' => $job->updated_at->toDateTimeString(),
						 'active_applications' => $job->active_applications ?? 0,
					];
			 }
			 
			 /**
			  * Retrieve jobs for a specific company.
			  */
			 public function getAllJobsForCompany(Request $request, int $companyId): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  if ($admin->company_id !== $companyId && !$admin->hasRole('super-admin')) {
								 return responseJson(403, 'Forbidden', 'You are not authorized to access this resource');
						  }
						  
						  $page = $request->get('page', 1);
						  $cacheKey = "company_{$companyId}_jobs_page_{$page}";
						  $jobs = Cache::store('redis')->remember($cacheKey, now()->addMinutes(15), fn() =>
						  JobListing::with(['company' => fn($query) => $query->select('id', 'name', 'logo')])
								->where('company_id', $companyId)
								->where('job_status', '!=', 'cancelled')
								->withCount([
									 'applications as active_applications' => fn($query) =>
									 $query->whereNotIn('status', ['rejected', 'pending']),
								])
								->paginate(10)
								->through(fn($job) => $this->mapJobData($job))
						  );
						  
						  if ($jobs->isEmpty()) {
								 return responseJson(404, 'No jobs found');
						  }
						  
						  return responseJson(200, 'Jobs retrieved successfully', $jobs);
					} catch (\Exception $e) {
						  Log::error('Get all jobs for company error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve jobs');
					}
			 }
			 
			 /**
			  * Retrieve details of a specific job.
			  */
			 public function show(int $jobId): JsonResponse
			 {
					try {
						  $cacheKey = "job_{$jobId}_details";
						  $job = Cache::store('redis')->remember($cacheKey, now()->addMinutes(10), fn() =>
						  JobListing::with(['company' => fn($query) => $query->select('id', 'name', 'logo')])
								->where('job_status', '!=', 'cancelled')
								->findOrFail($jobId)
						  );
						  
						  if (auth('admin')->check()) {
								 $admin = auth('admin')->user();
								 if (!$this->isAdminAuthorizedToShow($admin, $job)) {
										return responseJson(403, 'Forbidden', 'You do not have permission to view this job');
								 }
						  } elseif (!auth('api')->check()) {
								 return responseJson(403, 'Forbidden', 'You do not have permission to view this job');
						  }
						  
						  return responseJson(200, 'Job details retrieved', [
								'job' => $this->mapJobData($job),
								'logo' => url($job->company->logo),
						  ]);
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Job not found');
					} catch (\Exception $e) {
						  Log::error('Show job error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve job');
					}
			 }
			 
			 /**
			  * Check if an admin is authorized to view a job.
			  */
			 private function isAdminAuthorizedToShow($admin, JobListing $job): bool
			 {
					return $admin->hasRole('super-admin') ||
						 $admin->id === $job->company->admin_id ||
						 ($admin->hasAnyRole(['hr', 'coo']) && $admin->company_id === $job->company->id);
			 }
			 
			 /**
			  * Check if an admin is authorized to view jobs.
			  */
			 private function isAdminAuthorized($admin): bool
			 {
					return $admin->hasRole('super-admin') || $admin->hasPermissionTo('manage-company-jobs');
			 }
			 
			 /**
			  * Get validation rules and messages for job creation or update.
			  */
			 private function getJobValidationRules(): array
			 {
					return [
						 'rules' => [
							  'category_name' => ['required', 'string', 'exists:categories,name'],
							  'title' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s,.+\-\'\/]+$/'],
							  'job_type' => ['required', 'string', 'in:Full-time,Part-time,Internship,Contract'],
							  'salary' => ['required', 'numeric', 'min:1000', 'max:100000000'],
							  'location' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s,.+\-]+$/u'],
							  'job_status' => ['sometimes', 'string', 'in:open,closed'],
							  'description' => ['required', 'string', 'max:65535', 'regex:/^[a-zA-Z0-9\s\']+$/'],
							  'requirement' => ['required', 'string', 'max:65535', 'regex:/^[a-zA-Z0-9\s\']+$/'],
							  'benefits' => ['sometimes', 'string', 'max:65535', 'regex:/^[a-zA-Z\s\']+$/'],
							  'position' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z\s\']+$/'],
						 ],
						 'messages' => [
							  'category_name.required' => 'The category name is required.',
							  'category_name.exists' => 'The specified category does not exist.',
							  'title.required' => 'The job title is required.',
							  'title.max' => 'Job title cannot exceed 255 characters.',
							  'title.regex' => 'Job title contains invalid characters.',
							  'job_type.required' => 'The job type is required.',
							  'job_type.in' => 'Invalid job type.',
							  'salary.required' => 'The salary is required.',
							  'salary.min' => 'Salary must be at least 1000.',
							  'salary.max' => 'Salary cannot exceed 100,000,000.',
							  'location.required' => 'The location is required.',
							  'location.max' => 'Location cannot exceed 255 characters.',
							  'location.regex' => 'Location contains invalid characters.',
							  'job_status.in' => 'Invalid job status.',
							  'description.required' => 'The description is required.',
							  'description.max' => 'Description cannot exceed 65,535 characters.',
							  'description.regex' => 'Description contains invalid characters.',
							  'requirement.required' => 'The requirement is required.',
							  'requirement.max' => 'Requirement cannot exceed 65,535 characters.',
							  'requirement.regex' => 'Requirement contains invalid characters.',
							  'benefits.max' => 'Benefits cannot exceed 65,535 characters.',
							  'benefits.regex' => 'Benefits contain invalid characters.',
							  'position.required' => 'The position is required.',
							  'position.max' => 'Position cannot exceed 100 characters.',
							  'position.regex' => 'Position contains invalid characters.',
						 ],
					];
			 }
			 
			 /**
			  * Create a new job listing.
			  */
			 public function store(Request $request): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  if ($admin->hasRole('super-admin')) {
								 return responseJson(403, 'Forbidden', 'Super-admins cannot create jobs directly');
						  }
						  
						  if (!$admin->hasPermissionTo('manage-company-jobs')) {
								 return responseJson(403, 'Forbidden', 'You do not have permission to manage company jobs');
						  }
						  
						  if (!$admin->company_id) {
								 return responseJson(403, 'Forbidden', 'You must have a company to create jobs');
						  }
						  
						  $validationRules = $this->getJobValidationRules();
						  $validated = $request->validate($validationRules['rules'], $validationRules['messages']);
						  
						  $existingJob = JobListing::where('company_id', $admin->company_id)
								->where('title', $validated['title'])
								->where('position', $validated['position'])
								->first();
						  
						  if ($existingJob) {
								 return responseJson(409, 'Job already exists', 'A job with this title and position already exists');
						  }
						  
						  $job = DB::transaction(function () use ($admin, $validated) {
								 return JobListing::create(array_merge($validated, ['company_id' => $admin->company_id]));
						  });
						  
						  // Queue notifications to users
						  User::all()->each(fn($user) => $user->notify(
								new JobizzUserNotification(
									 title: 'New Job Posted',
									 body: "A new job, {$job->title}, is available at {$job->company->name}.",
									 data: ['job_title' => $job->title]
								)
						  ));
						  
						  // Invalidate caches
						  $this->invalidateJobCaches($job->id, $job->company_id);
						  
						  return responseJson(201, 'Job created successfully', [
								'job' => $this->mapJobData($job),
								'logo' => $job->company->logo,
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Resource not found', 'Category or company not found');
					} catch (\Exception $e) {
						  Log::error('Store job error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to create job');
					}
			 }
			 
			 /**
			  * Update an existing job listing.
			  */
			 public function update(Request $request, int $jobId): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  if ($admin->hasRole('super-admin')) {
								 return responseJson(403, 'Forbidden', 'Super-admins cannot update jobs directly');
						  }
						  
						  if (!$admin->hasPermissionTo('manage-company-jobs')) {
								 return responseJson(403, 'Forbidden', 'You do not have permission to manage company jobs');
						  }
						  
						  if (!$admin->company_id) {
								 return responseJson(403, 'Forbidden', 'You must have a company to update jobs');
						  }
						  
						  $job = JobListing::where('job_status', '!=', 'cancelled')
								->where('company_id', $admin->company_id)
								->findOrFail($jobId);
						  
						  $validationRules = $this->getJobValidationRules();
						  $validationRules['rules'] = array_map(fn($rule) =>
						  is_array($rule) ? array_merge(['sometimes'], array_filter($rule, fn($r) => $r !== 'required')) : $rule,
								$validationRules['rules']
						  );
						  $validated = $request->validate($validationRules['rules'], $validationRules['messages']);
						  
						  $originalData = $job->only(array_keys($validated));
						  $changes = array_diff_assoc($validated, $originalData);
						  
						  if (empty($changes)) {
								 return responseJson(200, 'No changes detected', [
									  'job' => $this->mapJobData($job),
									  'logo' => $job->company->logo,
									  'unchanged' => true,
								 ]);
						  }
						  
						  DB::transaction(function () use ($job, $validated) {
								 $job->update($validated);
						  });
						  
						  // Invalidate caches
						  $this->invalidateJobCaches($job->id, $job->company_id);
						  
						  return responseJson(200, 'Job updated successfully', [
								'job' => $this->mapJobData($job->fresh(['company'])),
								'logo' => $job->company->logo,
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Job not found');
					} catch (\Exception $e) {
						  Log::error('Update job error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to update job');
					}
			 }
			 
			 /**
			  * Mark a job as canceled and schedule its deletion.
			  */
			 public function destroy(int $jobId): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $job = JobListing::where('company_id', $admin->company_id)
								->findOrFail($jobId);
						  
						  if ($job->job_status === 'cancelled') {
								 return responseJson(400, 'Job already cancelled', 'This job has already been cancelled');
						  }
						  
						  if (!$this->isAuthorizedToDelete($admin, $job)) {
								 return responseJson(403, 'Forbidden', 'You do not have permission to delete this job');
						  }
						  
						  DB::transaction(function () use ($job) {
								 $job->update(['job_status' => 'cancelled']);
								 $job->applications->each(function ($application) {
										$application->update(['status' => 'rejected']);
										$application->statuses()->create([
											 'status' => 'rejected',
											 'feedback' => 'Job was cancelled by admin.',
										]);
								 });
						  });
						  
						  DeleteJobAndApplications::dispatch($jobId)->delay(now()->addDays(15));
						  Log::info("Scheduled deletion for job ID: {$jobId} after 15 days");
						  
						  // Invalidate caches
						  $this->invalidateJobCaches($jobId, $job->company_id);
						  
						  return responseJson(200, 'Job marked as cancelled and applications rejected. Scheduled for deletion in 15 days');
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Job not found');
					} catch (\Exception $e) {
						  Log::error('Destroy job error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to delete job');
					}
			 }
			 
			 /**
			  * Check if an admin is authorized to delete a job.
			  */
			 private function isAuthorizedToDelete($admin, JobListing $job): bool
			 {
					return $admin->hasPermissionTo('manage-company-jobs') && $admin->company_id === $job->company_id;
			 }
			 
			 /**
			  * Invalidate job and company-related caches.
			  */
			 private function invalidateJobCaches(int $jobId, int $companyId): void
			 {
					Cache::store('redis')->forget('open_jobs_count');
					Cache::store('redis')->forget('trending_jobs');
					Cache::store('redis')->forget('popular_jobs');
					Cache::store('redis')->forget("job_{$jobId}_details");
					Cache::store('redis')->forget("company_{$companyId}_details");
					Cache::store('redis')->forget('trending_companies');
					Cache::store('redis')->forget('popular_companies');
					
					// Invalidate user-specific recommended jobs
					$userIds = User::pluck('id');
					foreach ($userIds as $userId) {
						  Cache::store('redis')->forget("recommended_jobs_{$userId}");
					}
					
					// Invalidate admin jobs and company jobs pages
					$page = 1;
					while (Cache::store('redis')->has("admin_jobs_page_{$page}")) {
						  Cache::store('redis')->forget("admin_jobs_page_{$page}");
						  $page++;
					}
					$page = 1;
					while (Cache::store('redis')->has("company_{$companyId}_jobs_page_{$page}")) {
						  Cache::store('redis')->forget("company_{$companyId}_jobs_page_{$page}");
						  $page++;
					}
			 }
	  }