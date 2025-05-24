<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Http\Resources\StatusResource;
	  use App\Models\Application;
	  use App\Models\JobListing;
	  use App\Models\Profile;
	  use App\Notifications\JobizzUserNotification;
	  use Illuminate\Database\Eloquent\ModelNotFoundException;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Cache;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Validation\ValidationException;
	  
	  class ApplicationController extends Controller
	  {
			 /**
			  * Submit a new job application.
			  */
			 public function store(Request $request, int $profileId, int $jobId): JsonResponse
			 {
					try {
						  $user = auth('api')->user();
						  if (!$user) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $validated = $request->validate([
								'cover_letter' => ['nullable', 'string', 'max:1000'],
								'cv_id' => ['required', 'integer', 'exists:documents,id'],
						  ], $this->getValidationMessages());
						  
						  $profile = Profile::with(['documents' => fn($query) => $query->where('type', 'cv')])
								->where('user_id', $user->id)
								->findOrFail($profileId);
						  
						  $cv = $profile->documents->firstWhere('id', $validated['cv_id']);
						  if (!$cv) {
								 return responseJson(404, 'CV not found', 'CV not found or does not belong to this profile');
						  }
						  
						  $job = JobListing::where('job_status', '!=', 'cancelled')
								->findOrFail($jobId);
						  
						  if (Application::where('profile_id', $profile->id)->where('job_id', $jobId)->exists()) {
								 return responseJson(403, 'Forbidden', 'You have already applied for this job');
						  }
						  
						  $application = DB::transaction(function () use ($profile, $jobId, $validated, $cv) {
								 $application = Application::create([
									  'profile_id' => $profile->id,
									  'job_id' => $jobId,
									  'cover_letter' => $validated['cover_letter'] ?? 'No cover letter provided',
									  'resume_path' => $cv->path,
									  'status' => 'pending',
								 ]);
								 $application->statuses()->create(['status' => 'pending']);
								 return $application;
						  });
						  
						  // Invalidate caches
						  $this->invalidateApplicationCaches($application->id, $job->company_id, $profile->id);
						  
						  return responseJson(201, 'Application submitted successfully', [
								'application' => $this->mapApplicationData($application->load(['job', 'profile.user'])),
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Resource not found', 'Profile or job not found');
					} catch (\Exception $e) {
						  Log::error('Store application error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to submit application');
					}
			 }
			 
			 /**
			  * Retrieve applications for a user's profile.
			  */
			 public function getApplicationsForUser(Request $request, int $profileId): JsonResponse
			 {
					try {
						  $user = auth('api')->user();
						  if (!$user) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $profile = Profile::where('id', $profileId)->where('user_id', $user->id)->firstOrFail();
						  
						  $page = $request->get('page', 1);
						  $cacheKey = "applications_profile_{$profileId}_page_{$page}";
						  $applications = Cache::store('redis')->remember($cacheKey, now()->addMinutes(5), fn() =>
						  Application::with([
								'job' => fn($query) => $query->select('id', 'title', 'salary', 'company_id'),
								'job.company' => fn($query) => $query->select('id', 'name', 'logo'),
								'profile.user' => fn($query) => $query->select('id', 'name', 'email'),
						  ])
								->where('profile_id', $profile->id)
								->paginate(10)
								->through(fn($application) => $this->mapApplicationData($application))
						  );
						  
						  if ($applications->isEmpty()) {
								 return responseJson(404, 'No applications found');
						  }
						  
						  $statuses = [
								'pending', 'submitted', 'reviewed', 'screening-interview',
								'technical-interview', 'final-hr-interview', 'team-matching',
								'accepted', 'rejected', 'offer-letter',
						  ];
						  
						  $applicationsByStatus = [];
						  foreach ($statuses as $status) {
								 $count = $applications->where('status', $status)->count();
								 $applicationsByStatus[$status] = [
									  'applications' => $count ? $applications->where('status', $status)->values() : [],
									  'count' => $count,
								 ];
						  }
						  
						  return responseJson(200, 'Applications retrieved successfully', [
								'applications_by_status' => $applicationsByStatus,
								'total_count' => $applications->total(),
						  ]);
					} catch (ModelNotFoundException $e) {
						  return responseJson(403, 'Forbidden', 'You do not have permission to access this profile');
					} catch (\Exception $e) {
						  Log::error('Get applications for user error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve applications');
					}
			 }
			 
			 /**
			  * Retrieve all active applications for an admin's company.
			  */
			 public function index(): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  if (!$admin->hasPermissionTo('manage-applications')) {
								 return responseJson(403, 'Forbidden', 'Not authorized to access this resource');
						  }
						  
						  if (!$admin->company_id) {
								 return responseJson(403, 'Forbidden', 'No company associated with this account');
						  }
						  
						  $page = request()->get('page', 1);
						  $cacheKey = "active_applications_company_{$admin->company_id}_page_{$page}";
						  $applications = Cache::store('redis')->remember($cacheKey, now()->addMinutes(15), fn() =>
						  Application::whereHas('job', fn($query) =>
						  $query->where('company_id', $admin->company_id)
								->where('job_status', '!=', 'cancelled'))
								->with([
									 'job' => fn($query) => $query->select('id', 'title', 'salary'),
									 'profile.user' => fn($query) => $query->select('id', 'name', 'email'),
								])
								->whereNotIn('status', ['rejected'])
								->paginate(10)
								->through(fn($application) => $this->mapApplicationData($application))
						  );
						  
						  if ($applications->isEmpty()) {
								 return responseJson(404, 'No active applications found');
						  }
						  
						  Log::info("Retrieved {$applications->total()} active applications for company ID: {$admin->company_id}");
						  
						  return responseJson(200, 'Applications retrieved', [
								'applications' => $applications,
								'applications_count' => $applications->total(),
						  ]);
					} catch (\Exception $e) {
						  Log::error('Index applications error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve applications');
					}
			 }
			 
			 /**
			  * Retrieve cancelled (rejected) applications for an admin's company.
			  */
			 public function cancelledApplicationsForAdmin(): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  if (!$admin->hasPermissionTo('manage-applications') && !$admin->hasRole('super-admin')) {
								 return responseJson(403, 'Forbidden', 'Not authorized to access this resource');
						  }
						  
						  if (!$admin->company_id) {
								 return responseJson(403, 'Forbidden', 'No company associated with this account');
						  }
						  
						  $page = request()->get('page', 1);
						  $cacheKey = "rejected_applications_company_{$admin->company_id}_page_{$page}";
						  $applications = Cache::store('redis')->remember($cacheKey, now()->addMinutes(15), fn() =>
						  Application::whereHas('job', fn($query) =>
						  $query->where('company_id', $admin->company_id)
								->where('job_status', '!=', 'cancelled'))
								->with([
									 'job' => fn($query) => $query->select('id', 'title', 'salary'),
									 'profile.user' => fn($query) => $query->select('id', 'name', 'email'),
								])
								->where('status', 'rejected')
								->paginate(10)
								->through(fn($application) => $this->mapApplicationData($application))
						  );
						  
						  if ($applications->isEmpty()) {
								 return responseJson(404, 'No rejected applications found');
						  }
						  
						  Log::info("Retrieved {$applications->total()} rejected applications for company ID: {$admin->company_id}");
						  
						  return responseJson(200, 'Rejected applications retrieved', [
								'applications' => $applications,
								'applications_count' => $applications->total(),
						  ]);
					} catch (\Exception $e) {
						  Log::error('Cancelled applications error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve rejected applications');
					}
			 }
			 
			 /**
			  * Update the status of an application.
			  */
			 public function updateStatus(int $applicationId, Request $request): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  if (!$admin->hasPermissionTo('manage-applications')) {
								 return responseJson(403, 'Forbidden', 'Not authorized to update application status');
						  }
						  
						  $validated = $request->validate([
								'status' => ['required', 'string', 'in:submitted,reviewed,screening-interview,technical-interview,final-hr-interview,team-matching,accepted,rejected,offer-letter'],
								'feedback' => ['nullable', 'string', 'max:500'],
						  ], $this->getValidationMessages());
						  
						  $application = Application::whereHas('job', fn($query) =>
						  $query->where('company_id', $admin->company_id))
								->findOrFail($applicationId);
						  
						  $statusOrder = [
								'pending' => ['submitted'],
								'submitted' => ['reviewed'],
								'reviewed' => ['screening-interview'],
								'screening-interview' => ['technical-interview'],
								'technical-interview' => ['final-hr-interview'],
								'final-hr-interview' => ['team-matching'],
								'team-matching' => ['accepted', 'rejected'],
								'accepted' => ['offer-letter'],
								'rejected' => [],
								'offer-letter' => [],
						  ];
						  
						  $currentStatus = $application->statuses()->latest('updated_at')->first()->status ?? 'pending';
						  if (!in_array($validated['status'], $statusOrder[$currentStatus])) {
								 return responseJson(400, 'Invalid status transition', [
									  'message' => 'Invalid status transition. Next allowed statuses: ' . implode(', ', $statusOrder[$currentStatus]),
									  'current_status' => $currentStatus,
								 ]);
						  }
						  
						  $application = DB::transaction(function () use ($application, $validated) {
								 $application->update(['status' => $validated['status']]);
								 $application->statuses()->create([
									  'status' => $validated['status'],
									  'feedback' => $validated['feedback'] ?? 'No feedback provided',
								 ]);
								 return $application;
						  });
						  
						  $user = $application->profile->user;
						  $user->notify(
								new JobizzUserNotification(
									 title: 'Application Status Updated',
									 body: "Your application for {$application->job->title} is now {$validated['status']}.",
									 data: ['profile_name' => $application->profile->title_job]
								)
						  );
						  
						  // Invalidate caches
						  $this->invalidateApplicationCaches($application->id, $application->job->company_id, $application->profile_id);
						  
						  return responseJson(200, 'Application status updated successfully', [
								'id' => $application->id,
								'job_title' => $application->job->title,
								'user_name' => $application->profile->user->name,
								'user_email' => $application->profile->user->email,
								'current_status' => $validated['status'],
								'history' => StatusResource::collection($application->statuses),
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Application not found');
					} catch (\Exception $e) {
						  Log::error('Update status error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to update application status');
					}
			 }
			 
			 /**
			  * Restore a rejected application to submitted status.
			  */
			 public function restore(int $applicationId): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  if (!$admin->hasPermissionTo('manage-applications')) {
								 return responseJson(403, 'Forbidden', 'Not authorized to restore application');
						  }
						  
						  $application = Application::whereHas('job', fn($query) =>
						  $query->where('company_id', $admin->company_id)
								->where('job_status', '!=', 'cancelled'))
								->where('status', 'rejected')
								->findOrFail($applicationId);
						  
						  $application = DB::transaction(function () use ($application) {
								 $application->update(['status' => 'submitted']);
								 $application->statuses()->create([
									  'status' => 'submitted',
									  'feedback' => 'Application restored by admin',
								 ]);
								 return $application;
						  });
						  
						  $user = $application->profile->user;
						  $user->notify(
								new JobizzUserNotification(
									 title: 'Application Restored',
									 body: "Your application for {$application->job->title} has been restored to submitted status.",
									 data: ['profile_name' => $application->profile->title_job]
								)
						  );
						  
						  // Invalidate caches
						  $this->invalidateApplicationCaches($application->id, $application->job->company_id, $application->profile_id);
						  
						  return responseJson(200, 'Application restored successfully', [
								'id' => $application->id,
								'job_title' => $application->job->title,
								'user_name' => $application->profile->user->name,
								'user_email' => $application->profile->user->email,
								'current_status' => 'submitted',
								'history' => StatusResource::collection($application->statuses),
						  ]);
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Application not found');
					} catch (\Exception $e) {
						  Log::error('Restore application error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to restore application');
					}
			 }
			 
			 /**
			  * Retrieve status history for a user's application.
			  */
			 public function getStatusHistoryForUser(Request $request, int $profileId, int $applicationId): JsonResponse
			 {
					try {
						  $user = auth('api')->user();
						  if (!$user) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $profile = Profile::where('id', $profileId)->where('user_id', $user->id)->firstOrFail();
						  
						  $cacheKey = "application_{$applicationId}_status_history";
						  $application = Cache::store('redis')->remember($cacheKey, now()->addMinutes(10), fn() =>
						  Application::with([
								'job' => fn($query) => $query->select('id', 'title', 'salary', 'location'),
								'job.company' => fn($query) => $query->select('id', 'name', 'logo'),
								'statuses' => fn($query) => $query->select('id', 'application_id', 'status', 'feedback', 'updated_at'),
						  ])
								->where('profile_id', $profile->id)
								->where('id', $applicationId)
								->firstOrFail()
						  );
						  
						  return responseJson(200, 'Application status history retrieved successfully', [
								'id' => $application->id,
								'job' => $application->job,
								'company' => $application->job->company,
								'status' => StatusResource::collection($application->statuses),
						  ]);
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Application or profile not found');
					} catch (\Exception $e) {
						  Log::error('Get status history error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve status history');
					}
			 }
			 
			 /**
			  * Reject an application.
			  */
			 public function destroy(int $applicationId): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  if (!$admin->hasPermissionTo('manage-applications')) {
								 return responseJson(403, 'Forbidden', 'Not authorized to reject application');
						  }
						  
						  $application = Application::whereHas('job', fn($query) =>
						  $query->where('company_id', $admin->company_id)
								->where('job_status', '!=', 'cancelled'))
								->where('status', '!=', 'rejected')
								->findOrFail($applicationId);
						  
						  $application = DB::transaction(function () use ($application) {
								 $application->update(['status' => 'rejected']);
								 $application->statuses()->create([
									  'status' => 'rejected',
									  'feedback' => 'Application rejected by admin',
								 ]);
								 return $application;
						  });
						  
						  $user = $application->profile->user;
						  $user->notify(
								new JobizzUserNotification(
									 title: 'Application Rejected',
									 body: "Your application for {$application->job->title} has been rejected.",
									 data: ['profile_name' => $application->profile->title_job]
								)
						  );
						  
						  // Invalidate caches
						  $this->invalidateApplicationCaches($application->id, $application->job->company_id, $application->profile_id);
						  
						  return responseJson(200, 'Application rejected successfully', [
								'id' => $application->id,
								'job_title' => $application->job->title,
								'user_name' => $application->profile->user->name,
								'user_email' => $application->profile->user->email,
								'current_status' => 'rejected',
						  ]);
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Application not found');
					} catch (\Exception $e) {
						  Log::error('Destroy application error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to reject application');
					}
			 }
			 
			 /**
			  * Map application data for consistent response format.
			  */
			 private function mapApplicationData(Application $application): array
			 {
					return [
						 'id' => $application->id,
						 'resume_path' => $application->resume_path,
						 'status' => $application->status,
						 'created_at' => $application->created_at->format('Y-m-d'),
						 'job' => $application->job ? [
							  'id' => $application->job->id,
							  'title' => $application->job->title,
							  'salary' => $application->job->salary,
							  'company' => $application->job->company ? [
									'id' => $application->job->company->id,
									'name' => $application->job->company->name,
									'logo' => $application->job->company->logo,
							  ] : null,
						 ] : null,
						 'user' => $application->profile->user ? [
							  'id' => $application->profile->user->id,
							  'name' => $application->profile->user->name,
							  'email' => $application->profile->user->email,
						 ] : null,
					];
			 }
			 
			 /**
			  * Get validation messages for application-related operations.
			  */
			 private function getValidationMessages(): array
			 {
					return [
						 'cover_letter.max' => 'Cover letter cannot exceed 1000 characters.',
						 'cv_id.required' => 'CV ID is required.',
						 'cv_id.exists' => 'The specified CV does not exist.',
						 'status.required' => 'Status is required.',
						 'status.in' => 'Invalid status value.',
						 'feedback.max' => 'Feedback cannot exceed 500 characters.',
					];
			 }
			 
			 /**
			  * Invalidate application, job, and company-related caches.
			  */
			 private function invalidateApplicationCaches(int $applicationId, int $companyId, int $profileId): void
			 {
					Cache::store('redis')->forget("application_{$applicationId}_status_history");
					Cache::store('redis')->forget("job_{$application->job_id}_details");
					Cache::store('redis')->forget("company_{$companyId}_details");
					Cache::store('redis')->forget('trending_companies');
					Cache::store('redis')->forget('popular_companies');
					
					// Invalidate profile applications pages
					$page = 1;
					while (Cache::store('redis')->has("applications_profile_{$profileId}_page_{$page}")) {
						  Cache::store('redis')->forget("applications_profile_{$profileId}_page_{$page}");
						  $page++;
					}
					
					// Invalidate company applications pages
					$page = 1;
					while (Cache::store('redis')->has("active_applications_company_{$companyId}_page_{$page}")) {
						  Cache::store('redis')->forget("active_applications_company_{$companyId}_page_{$page}");
						  $page++;
					}
					$page = 1;
					while (Cache::store('redis')->has("rejected_applications_company_{$companyId}_page_{$page}")) {
						  Cache::store('redis')->forget("rejected_applications_company_{$companyId}_page_{$page}");
						  $page++;
					}
					
					// Invalidate job and admin jobs pages
					$page = 1;
					while (Cache::store('redis')->has("company_{$companyId}_jobs_page_{$page}")) {
						  Cache::store('redis')->forget("company_{$companyId}_jobs_page_{$page}");
						  $page++;
					}
					$page = 1;
					while (Cache::store('redis')->has("admin_jobs_page_{$page}")) {
						  Cache::store('redis')->forget("admin_jobs_page_{$page}");
						  $page++;
					}
			 }
	  }