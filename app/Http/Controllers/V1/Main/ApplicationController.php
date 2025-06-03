<?php
	  
	  namespace App\Http\Controllers\V1\Main;
	  
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
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Validation\ValidationException;
	  
	  class ApplicationController extends Controller
	  {
			 /**
			  * Submit a new job application.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $jobId
			  *
			  * @return JsonResponse
			  */
			 public function store(Request $request, int $profileId, int $jobId
			 ): JsonResponse {
					try {
						  if (!auth('api')->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $user = auth('api')->user();
						  
						  $validated = $request->validate([
								'cover_letter' => 'nullable|string|max:1000',
								'cv_id'        => 'required|integer|exists:documents,id',
						  ]);
						  
						  $profile = Profile::with('documents')->find($profileId);
						  if (!$profile) {
								 return responseJson(
									  404, 'Profile not found', 'Profile not found'
								 );
						  }
						  
						  if ($user->id !== $profile->user_id) {
								 return responseJson(
									  403, 'Forbidden',
									  'This profile does not belong to you'
								 );
						  }
						  
						  $cv = $profile->documents()->where('type', 'cv')->where(
								'id', $validated['cv_id']
						  )->first();
						  if (!$cv) {
								 return responseJson(
									  404, 'CV not found',
									  'CV not found or does not belong to this profile'
								 );
						  }
						  
						  $job = JobListing::where('job_status', '!=', 'cancelled')
								->find($jobId);
						  if (!$job) {
								 return responseJson(
									  404, 'Job not found', 'Job not found'
								 );
						  }
						  
						  if (Application::where('profile_id', $profile->id)->where(
								'job_id', $jobId
						  )->exists()
						  ) {
								 return responseJson(
									  403, 'Forbidden',
									  'You have already applied for this job'
								 );
						  }
						  
						  $application = Application::create([
								'profile_id'   => $profile->id,
								'job_id'       => $jobId,
								'cover_letter' => $validated['cover_letter'] ??
									 'No cover letter provided',
								'resume_path'  => $cv->path,
								'status'       => 'pending',
						  ]);
						  
						  $application->statuses()->create(['status' => 'pending']);
						  
						  return responseJson(
								201, 'Application submitted successfully', [
									 'application' => $application->load(
										  ['job', 'profile.user']
									 ),
								]
						  );
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Resource not found', 'Profile or CV not found'
						  );
					} catch (\Exception $e) {
						  Log::error('Store application error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Retrieve applications for a user's profile.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  *
			  * @return JsonResponse
			  */
			 public function getApplicationsForUser(Request $request, int $profileId
			 ): JsonResponse {
					try {
						  if (!auth('api')->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $user = auth('api')->user();
						  $profile = Profile::where('id', $profileId)->where(
								'user_id', $user->id
						  )->first();
						  
						  if (!$profile) {
								 return responseJson(
									  403, 'Forbidden',
									  'You do not have permission to access this profile'
								 );
						  }
						  
						  $statuses = [
								'pending', 'reviewed', 'accepted', 'rejected',
								'submitted',
								'team-matching', 'final-hr-interview',
								'technical-interview', 'screening-interview'
						  ];
						  
						  $applications = Cache::remember(
								"applications_profile_{$profileId}",
								now()->addMinutes(5),
								fn() => Application::with([
									 'job'          => fn($query) => $query->select(
										  'id', 'title', 'salary', 'company_id'
									 ),
									 'job.company'  => fn($query) => $query->select(
										  'id', 'name', 'logo'
									 ),
									 'profile.user' => fn($query) => $query->select(
										  'id', 'name', 'email'
									 ),
								])
									 ->where('profile_id', $profile->id)
									 ->get()
						  );
						  
						  $applicationsByStatus = [];
						  foreach ($statuses as $status) {
								 $apps = $applications->where('status', $status);
								 $formattedApplications = $apps->map(
									  fn($application) => [
											'id' => $application->id,
											'resume_path' => $application->resume_path,
											'status' => $application->status,
											'created_at' => $application->created_at->format('Y-m-d'),
											'job' => $application->job,
											'user' => $application->profile->user,
									  ]
								 )->values(); // Reset keys to ensure sequential array
								 
								 $applicationsByStatus[$status] = [
									  'applications' => $formattedApplications,
									  'count' => $formattedApplications->count(),
								 ];
						  }
						  
						  return responseJson(
								200, 'Applications retrieved successfully',
								$applicationsByStatus
						  );
					} catch (\Exception $e) {
						  Log::error(
								'Get applications for user error: ' . $e->getMessage()
						  );
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Retrieve all active applications for an admin's company.
			  *
			  * @return JsonResponse
			  */
			 public function index(): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  if (!$admin->hasPermissionTo('manage-applications'))
						  {
								 return responseJson(
									  403, 'Forbidden',
									  'Not authorized to access this resource'
								 );
						  }
						  
						  if (!$admin->company_id) {
								 return responseJson(
									  403, 'Forbidden',
									  'No company associated with this account'
								 );
						  }
						  
						  $applications = Application::whereHas(
								'job', fn($query) => $query
								->where('company_id', $admin->company_id)
								->where('job_status', '!=', 'cancelled')
						  )
								->with([
									 'job'          => fn($query) => $query->select(
										  'id', 'title', 'salary'
									 ),
									 'profile.user' => fn($query) => $query->select(
										  'id', 'name', 'email'
									 ),
								])
								->where('status', '!=', 'rejected')
								->get();
						  
						  $formattedApplications = $applications->map(
								fn($application) => [
									 'id'          => $application->id,
									 'resume_path' => $application->resume_path,
									 'status'      => $application->status,
									 'created_at'  => $application->created_at->format(
										  'Y-m-d'
									 ),
									 'job'         => $application->job,
									 'user'        => $application->profile->user,
								]
						  );
						  
						  if ($formattedApplications->isEmpty()) {
								 return responseJson(
									  404, 'No applications found',
									  'No applications found'
								 );
						  }
						  
						  Log::info(
								"Retrieved {$formattedApplications->count()} active applications for company ID: {$admin->company_id}"
						  );
						  
						  return responseJson(200, 'Applications retrieved', [
								'applications'       => $formattedApplications->values(
								),
								'applications_count' => $formattedApplications->count(),
						  ]);
					} catch (\Exception $e) {
						  Log::error('Index applications error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Retrieve cancelled (rejected) applications for an admin's company.
			  *
			  * @return JsonResponse
			  */
			 public function cancelledApplicationsForAdmin(): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  if (!$admin->hasPermissionTo('manage-applications')
								&& !$admin->hasRole('super-admin')
						  ) {
								 return responseJson(
									  403, 'Forbidden',
									  'Not authorized to access this resource'
								 );
						  }
						  
						  if (!$admin->company_id) {
								 return responseJson(
									  403, 'Forbidden',
									  'No company associated with this account'
								 );
						  }
						  
						  $applications = Application::whereHas(
								'job', fn($query) => $query
								->where('company_id', $admin->company_id)
								->where('job_status', '!=', 'cancelled')
						  )
								->with([
									 'job'          => fn($query) => $query->select(
										  'id', 'title', 'salary'
									 ),
									 'profile.user' => fn($query) => $query->select(
										  'id', 'name', 'email'
									 ),
								])
								->where('status', 'rejected')
								->get();
						  
						  $formattedApplications = $applications->map(
								fn($application) => [
									 'id'          => $application->id,
									 'resume_path' => $application->resume_path,
									 'status'      => $application->status,
									 'created_at'  => $application->created_at->format(
										  'Y-m-d'
									 ),
									 'job'         => $application->job,
									 'user'        => $application->profile->user,
								]
						  );
						  
						  if ($formattedApplications->isEmpty()) {
								 return responseJson(
									  404, 'No rejected applications found',
									  'No rejected applications found'
								 );
						  }
						  
						  Log::info(
								"Retrieved {$formattedApplications->count()} rejected applications for company ID: {$admin->company_id}"
						  );
						  
						  return responseJson(
								200, 'Rejected applications retrieved', [
									 'applications'       => $formattedApplications->values(
									 ),
									 'applications_count' => $formattedApplications->count(
									 ),
								]
						  );
					} catch (\Exception $e) {
						  Log::error(
								'Cancelled applications error: ' . $e->getMessage()
						  );
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Update the status of an application.
			  *
			  * @param int     $applicationId
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function updateStatus(int $applicationId, Request $request
			 ): JsonResponse {
					try {
						  if (!auth('admin')->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $admin = auth('admin')->user();
						  if (!$admin->hasPermissionTo('manage-applications')) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $validated = $request->validate([
								'status'   => 'required|string|in:submitted,reviewed,screening-interview,technical-interview,final-hr-interview,team-matching,accepted,rejected,offer-letter',
								'feedback' => 'nullable|string|max:500',
						  ]);
						  
						  $statusOrder = [
								'pending'             => ['submitted'],
								'submitted'           => ['reviewed'],
								'reviewed'            => ['screening-interview'],
								'screening-interview' => ['technical-interview'],
								'technical-interview' => ['final-hr-interview'],
								'final-hr-interview'  => ['team-matching'],
								'team-matching'       => ['accepted', 'rejected'],
								'accepted'            => ['offer-letter'],
								'rejected'            => [],
								'offer-letter'        => [],
						  ];
						  
						  $application = Application::whereHas(
								'job', fn($query) => $query
								->where('company_id', $admin->company_id)
						  )
								->find($applicationId);
						  
						  if (!$application) {
								 return responseJson(
									  404, 'Application not found',
									  'Application not found'
								 );
						  }
						  
						  $latestStatus = $application->statuses()->latest(
								'updated_at'
						  )->first();
						  $currentStatus = $latestStatus ? $latestStatus->status
								: 'pending';
						  
						  if (!in_array(
								$validated['status'], $statusOrder[$currentStatus]
						  )
						  ) {
								 return responseJson(400, 'Invalid status transition', [
									  'message'        => 'Invalid status transition. Next allowed statuses: '
											. implode(', ', $statusOrder[$currentStatus]),
									  'current_status' => $currentStatus,
								 ]);
						  }
						  
						  $application->update(['status' => $validated['status']]);
						  $application->statuses()->create([
								'status'   => $validated['status'],
								'feedback' => $validated['feedback']??'No feedback provided',
						  ]);
						  
						  $user = $application->profile->user;
						  $user->notify(
								new JobizzUserNotification(
									 title: 'Application Status Updated',
									 body: "Your application for {$application->job->title} is now {$validated['status']}.",
									 data: ['profile_name' => $application->profile->title_job]
								)
						  );
						  
						  return responseJson(
								200, 'Application status updated successfully', [
									 'id'             => $application->id,
									 'job_title'      => $application->job->title,
									 'user_name'      => $application->profile->user->name,
									 'user_email'     => $application->profile->user->email,
									 'current_status' => $validated['status'],
									 'history'        => StatusResource::collection(
										  $application->statuses
									 ),
								]
						  );
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Application not found', 'Application not found'
						  );
					} catch (\Exception $e) {
						  Log::error('Update status error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Restore a rejected application to submitted status.
			  *
			  * @param int $applicationId
			  *
			  * @return JsonResponse
			  */
			 public function restore(int $applicationId): JsonResponse
			 {
					try {
						  if (!auth('admin')->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $admin = auth('admin')->user();
						  if (!$admin->hasPermissionTo('manage-applications')) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $application = Application::where('id', $applicationId)
								->whereHas(
									 'job', fn($query) => $query->where(
									 'company_id', $admin->company_id
								)->where('job_status', '!=', 'cancelled')
								)
								->where('status', 'rejected')
								->first();
						  
						  if (!$application) {
								 return responseJson(
									  404, 'Application not found',
									  'Application not found'
								 );
						  }
						  
						  $application->update(['status' => 'submitted']);
						  $application->statuses()->create([
								'status'   => 'submitted',
								'feedback' => 'Application restored by admin',
						  ]);
						  
						  $user = $application->profile->user;
						  $user->notify(
								new JobizzUserNotification(
									 title: 'Application Restored',
									 body: "Your application for {$application->job->title} has been restored to submitted status.",
									 data: ['profile_name' => $application->profile->title_job]
								)
						  );
						  
						  return responseJson(
								200, 'Application restored successfully', [
									 'id'             => $application->id,
									 'job_title'      => $application->job->title,
									 'user_name'      => $application->profile->user->name,
									 'user_email'     => $application->profile->user->email,
									 'current_status' => 'submitted',
									 'history'        => StatusResource::collection(
										  $application->statuses
									 ),
								]
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Application not found', 'Application not found'
						  );
					} catch (\Exception $e) {
						  Log::error(
								'Restore application error: ' . $e->getMessage()
						  );
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Retrieve status history for a user's application.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $applicationId
			  *
			  * @return JsonResponse
			  */
			 public function getStatusHistoryForUser(Request $request, int $profileId,
				  int $applicationId
			 ): JsonResponse {
					try {
						  if (!auth('api')->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $user = auth('api')->user();
						  $profile = Profile::where('id', $profileId)->where(
								'user_id', $user->id
						  )->first();
						  
						  if (!$profile) {
								 return responseJson(
									  403, 'Forbidden',
									  'You do not have permission to access this profile'
								 );
						  }
						  
						  $application = Application::with([
								'job'         => fn($query) => $query->select(
									 'id', 'title', 'salary', 'location'
								),
								'job.company' => fn($query) => $query->select(
									 'id', 'name', 'logo'
								),
								'statuses'    => fn($query) => $query->select(
									 'id', 'application_id', 'status', 'feedback',
									 'updated_at'
								),
						  ])
								->where('profile_id', $profile->id)
								->where('id', $applicationId)
								->first();
						  
						  if (!$application) {
								 return responseJson(
									  404, 'Application not found',
									  'Application not found'
								 );
						  }
						  
						  return responseJson(
								200,
								'Application status history retrieved successfully', [
									 'id'      => $application->id,
									 'job'     => $application->job,
									 'company' => $application->job->company,
									 'status'  => StatusResource::collection(
										  $application->statuses
									 ),
								]
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Application not found', 'Application not found'
						  );
					} catch (\Exception $e) {
						  Log::error('Get status history error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Reject an application.
			  *
			  * @param int $applicationId
			  *
			  * @return JsonResponse
			  */
			 public function destroy(int $applicationId): JsonResponse
			 {
					try {
						  if (!auth('admin')->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $admin = auth('admin')->user();
						  if (!$admin->hasPermissionTo('manage-applications')) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $application = Application::where('id', $applicationId)
								->whereHas(
									 'job', fn($query) => $query->where(
									 'company_id', $admin->company_id
								)->where('job_status', '!=', 'cancelled')
								)
								->where('status', '!=', 'rejected')
								->first();
						  
						  if (!$application) {
								 return responseJson(
									  404, 'Application not found',
									  'Application not found'
								 );
						  }
						  
						  $application->update(['status' => 'rejected']);
						  $application->statuses()->create([
								'status'   => 'rejected',
								'feedback' => 'Application rejected by admin',
						  ]);
						  
						  $user = $application->profile->user;
						  $user->notify(
								new JobizzUserNotification(
									 title: 'Application Rejected',
									 body: "Your application for {$application->job->title} has been rejected.",
									 data: ['profile_name' => $application->profile->title_job]
								)
						  );
						  
						  return responseJson(
								200, 'Application rejected successfully', [
									 'id'             => $application->id,
									 'job_title'      => $application->job->title,
									 'user_name'      => $application->profile->user->name,
									 'user_email'     => $application->profile->user->email,
									 'current_status' => 'rejected',
								]
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Application not found', 'Application not found'
						  );
					} catch (\Exception $e) {
						  Log::error(
								'Destroy application error: ' . $e->getMessage()
						  );
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
	  }