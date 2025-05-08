<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Http\Resources\StatusResource;
	  use App\Models\Application;
	  use App\Models\ApplicationStatusHistory;
	  use App\Models\JobListing as Job;
	  use App\Models\Profile;
	  use Illuminate\Database\Eloquent\ModelNotFoundException;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Validation\ValidationException;
	  
	  class ApplicationController extends Controller
	  {
			 public function store(Request $request, $profileId, $jobId
			 ): JsonResponse {
					try {
						  // Check authentication
						  if (!auth('api')->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  // Validate request data
						  $validated = $request->validate([
								'cover_letter' => 'sometimes|string|max:1000',
								'cv_id'        => 'required|integer|exists:documents,id',
						  ]);
						  // Find the profile
						  $profile = Profile::with('documents')->findOrFail(
								$profileId
						  );
						  // Authorization check: Ensure the current user owns this profile
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(
									  403, 'Forbidden',
									  'This profile does not belong to you.'
								 );
						  }
						  // Verify that the CV belongs to this profile
						  $cv = $profile->documents()->where('type', 'cv')
								->where('id', $validated['cv_id'])
								->first();
						  if (!$cv) {
								 return responseJson(
									  404,
									  'CV not found or does not belong to this profile',
									  'CV not found or does not belong to this profile'
								 );
						  }
						  if (!isset($validated['cover_letter'])) {
								 $validated['cover_letter'] = 'no thing';
						  }
						  $job = Job::find($jobId)->where(
								'job_status', '!=', 'cancelled'
						  );
						  if (!$job) {
								 return responseJson(
									  404, 'Job not found', 'Job not found'
								 );
						  }
						  $existsApplication = Application::Where(
								'profile_id', $profile->id
						  )->where('job_id', $jobId)->exists();
						  // Check if the user has already applied for this job
						  if ($existsApplication) {
								 return responseJson(
									  403, 'Forbidden',
									  'You have already applied for this job.'
								 );
						  }
						  // Create application
						  $application = Application::create([
								'profile_id'   => $profile->id,
								'job_id'       => $jobId,
								'cover_letter' => $validated['cover_letter']
									 ?: 'No thing',
								'resume_path'  => $cv->path,
								'status'       => 'pending', // Initial status
						  ]);
						  // Record initial status history
						  $application->statuses()->create([
								'status' => 'pending',
						  ]);
						  return responseJson(
								201, 'Application submitted successfully', [
									 'application' => $application,
								]
						  );
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Profile or CV not found',
								'Profile or CV not found'
						  );
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, 'Server Error', $errorMessage);
					}
			 }
			 
			 public function getApplicationsForUser(Request $request, $profileId
			 ): JsonResponse {
					try {
						  // Check authentication
						  if (!auth('api')->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $user = auth('api')->user();
						  
						  // Find profile with authorization check
						  $profile = Profile::where('id', $profileId)
								->where('user_id', $user->id)
								->first();
						  
						  if (!$profile) {
								 return responseJson(
									  403, 'Forbidden',
									  'You do not have permission to this profile'
								 );
						  }
						  // Define the statuses you want to fetch
						  $statuses = ['pending', 'reviewed', 'accepted', 'rejected',
											'submitted', 'team-matching',
											'final-hr-interview', 'technical-interview',
											'screening-interview'];
						  
						  $applications = Application::with([
								'job:id,title,salary,company_id',
								'job.company:id,name,logo',
								'profile.user:id,name,email'
						  ])
								->where('profile_id', $profile->id)
								->get()
								->groupBy('status');
						  
						  $applicationsByStatus = [];
						  foreach ($statuses as $status) {
								 $apps = $applications->get(
									  $status, collect()
								 ); // Default to empty collection
								 $formattedApplications = $apps->map(
									  function ($application) {
											 return [
												  'id'          => $application->id,
												  'resume_path' => $application->resume_path,
												  'status'      => $application->status,
												  'created_at'  => $application->created_at->format(
														'Y-m-d'
												  ),
												  'job'         => $application->job,
												  'user'        => $application->profile->user
														?? null,
											 ];
									  }
								 );
								 $applicationsByStatus[$status] = [
									  'applications' => $formattedApplications,
									  'count'        => $formattedApplications->count(),
								 ];
						  }
						  
						  return responseJson(
								200, 'Applications retrieved successfully',
								$applicationsByStatus
						  );
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, 'Server Error', $errorMessage);
					}
			 }
			 
			 public function index(): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  
						  // Check authentication and permissions
						  if (!$admin->hasPermissionTo('manage-applications')
								&& $admin->hasRole('super-admin')
						  ) {
								 return responseJson(
									  403, 'Forbidden',
									  'Not authorized to access this resource'
								 );
						  }
						  // Verify admin has a company association
						  if (!$admin->company_id) {
								 return responseJson(
									  403, 'Forbidden',
									  'No company associated with this account'
								 );
						  }
						  // Log the company ID for debugging
						  Log::info('Admin company_id: ' . $admin->company_id);
						  
						  // Get applications for the admin's company with specific fields
						  $applications = Application::whereHas(
								'job', function ($query) use ($admin) {
								 $query->where('company_id', $admin->company_id)
									  ->where('job_status', '!=','cancelled');
						  }
						  )->with([
								'job:id,title,salary',
								// Select specific fields from jobListing
								'profile.user:id,name,email',
								// Select specific fields from user via profile
						  ])->where('status', '!=', 'rejected')->get();
						  
						  // Transform the collection to include only desired fields
						  $formattedApplications = $applications->map(
								function ($application) {
									  return [
											'id'          => $application->id,
											'resume_path' => $application->resume_path,
											'status'      => $application->status,
											'created_at'  => $application->created_at->format(
												 'Y-m-d'
											), // Format date as per casts
											'job'         => $application->job,
											// Include jobListing with selected fields
											'user'        => $application->profile->user ??
												 null, // Directly include user fields
									  ];
								}
						  );
						  
						  // Log the number of applications found
						  Log::info(
								'Applications found: ' . $formattedApplications->count()
						  );
						  
						  // Check if there are no applications
						  if ($formattedApplications->isEmpty()) {
								 return responseJson(
									  404, 'No applications found',
									  'No applications found'
								 );
						  }
						  
						  // Return the formatted response without pagination
						  return responseJson(200, 'Applications retrieved', [
								'applications'       => $formattedApplications->values(
								),
								'applications_count' => $formattedApplications->count(),
						  ]);
						  
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, 'Server Error', $errorMessage);
					}
			 }
			 
			 public function cancelledApplicationsForAdmin(): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  
						  // Check authentication and permissions
						  if (!$admin->hasPermissionTo('manage-applications')
								&& $admin->hasRole('super-admin')
						  ) {
								 return responseJson(
									  403, 'Forbidden',
									  'Not authorized to access this resource'
								 );
						  }
						  // Verify admin has a company association
						  if (!$admin->company_id) {
								 return responseJson(
									  403, 'Forbidden',
									  'No company associated with this account'
								 );
						  }
						  
						  // Log the company ID for debugging
						  Log::info('Admin company_id: ' . $admin->company_id);
						  
						  // Get applications for the admin's company with specific fields
						  $applications = Application::whereHas(
								'job', function ($query) use ($admin) {
								 $query->where('company_id', $admin->company_id)
									  ->where('job_status', '!=','cancelled');
						  }
						  )->with([
								'job:id,title,salary',
								// Select specific fields from jobListing
								'profile.user:id,name,email',
								// Select specific fields from user via profile
						  ])->where('status', '=', 'rejected')->get();
						  
						  
						  // Transform the collection to include only desired fields
						  $formattedApplications = $applications->map(
								function ($application) {
									  return [
											'id'          => $application->id,
											'resume_path' => $application->resume_path,
											'status'      => $application->status,
											'created_at'  => $application->created_at->format(
												 'Y-m-d'
											), // Format date as per casts
											'job'         => $application->job,
											// Include jobListing with selected fields
											'user'        => $application->profile->user ??
												 null, // Directly include user fields
									  ];
								}
						  );
						  
						  // Log the number of applications found
						  Log::info(
								'Applications found: ' . $formattedApplications->count()
						  );
						  
						  // Check if there are no applications
						  if ($formattedApplications->isEmpty()) {
								 return responseJson(
									  404, 'No applications found',
									  'No applications found'
								 );
						  }
						  
						  // Return the formatted response without pagination
						  return responseJson(200, 'Applications retrieved', [
								'applications'       => $formattedApplications->values(
								),
								'applications_count' => $formattedApplications->count(),
						  ]);
						  
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, 'Server Error', $errorMessage);
					}
			 }
			 
			 public function updateStatus($applicationId, Request $request
			 ): JsonResponse {
					try {
						  // Check authentication
						  if (!auth('admin')->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $admin = auth('admin')->user();
						  
						  // Check authorization
						  if (!$admin->hasPermissionTo('manage-applications')) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  // Validate request data
						  $validated = $request->validate([
								'status'   => 'required|string|in:submitted,reviewed,screening-interview,technical-interview,final-hr-interview,team-matching,accepted,rejected,offer-letter',
								'feedback' => 'sometimes|string|max:500',
						  ]);
						  
						  // Define the allowed status progression
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
								// No further steps after rejection
								'offer-letter'        => [], // End state
						  ];
						  
						  // Fetch the latest status history for the application
						  $latestStatus = ApplicationStatusHistory::where(
								'application_id', $applicationId
						  )
								->latest('updated_at')
								->first();
						  
						  $currentStatus = $latestStatus ? $latestStatus->status
								: 'pending'; // Default to 'submitted' if no history
						  
						  // Check if the new status is valid in the progression
						  if (!in_array(
								$validated['status'], $statusOrder[$currentStatus]
						  )
						  ) {
								 return responseJson(400, 'Invalid Status', [
									  'message'        => 'Invalid status transition. Next allowed statuses are: '
											. implode(', ', $statusOrder[$currentStatus]),
									  'current_status' => $currentStatus,
								 ]);
						  }
						  
						  // Find the application
						  $application = Application::findOrFail($applicationId);
						  
						  // Update application status
						  $application->update([
								'status' => $validated['status'],
						  ]);
						  
						  // Record status history
						  $application->statuses()->create([
								'status'   => $validated['status'],
								'feedback' => $validated['feedback'] ?? null,
						  ]);
						  
						  return responseJson(
								200, 'Application status updated successfully', [
									 'id'             => $application->id,
									 'job_title'      => $application->job->title,
									 'user_name'      => $application->profile->user->name,
									 'user_email'     => $application->profile->user->email,
									 'current_status' => $validated['status'],
									 'history'        => $application->statuses,
								]
						  );
						  
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, 'Server Error', $errorMessage);
					}
			 }
			 
			 public function restore($applicationId): JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth('admin')->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  $admin = auth('admin')->user();
						  // Check authorization
						  if (!$admin->hasPermissionTo('manage-applications')) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }

						  // Find the application
						  $application = Application::where('id', $applicationId)
								->whereHas('job', function ($query) use ($admin) {
									  $query->where('company_id', $admin->company_id)
											->where('job_status', '!=', 'cancelled');
								})
								->where('status', '=', 'rejected')
								->firstOrFail();
						  
						  // Update application status
						  $application->update([
								'status' => 'submitted',
						  ]);
						  
						  // Record status history
						  $application->statuses()->create([
								'status'   => 'submitted',
								'feedback' =>'admin restore this application',
						  ]);
						  
						  return responseJson(
								200, 'Application status updated successfully', [
									 'id'             => $application->id,
									 'job_title'      => $application->job->title,
									 'user_name'      => $application->profile->user->name,
									 'user_email'     => $application->profile->user->email,
									 'current_status' => 'submitted',
									 'history'        => $application->statuses,
								]
						  );
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  // Handle case where no application matches the criteria
						  return responseJson(404, 'Not Found', 'Application not found');
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, 'Server Error', $errorMessage);
					}
					}
			 
			 public function getStatusHistoryForUser(Request $request, $profileId,
				  $applicationId
			 ): JsonResponse {
					try {
						  // Check authentication
						  if (!auth('api')->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $user = auth('api')->user();
						  
						  // Find profile with authorization check
						  $profile = Profile::where('id', $profileId)
								->where('user_id', $user->id)
								->first();
						  
						  if (!$profile) {
								 return responseJson(
									  403, 'Forbidden',
									  'You do not have permission to this profile'
								 );
						  }
						  // Get applications with specific relationships and fields
						  $application = Application::with([
								'Job'         => function ($query) {
									  $query->select('id', 'title', 'salary', 'location')
											->withOut('company');
								},
								'job.company' => function ($query) {
									  $query->select('id', 'name', 'logo');
								},
								'statuses'    => function ($query) {
									  $query->select(
											'id', 'application_id', 'status', 'updated_at'
									  );
								}
						  ])->where('profile_id', $profile->id)
								->where('id', $applicationId)
								->firstOrFail();
						  // Format statuses using a resource (optional, if using StatusResource)
						  return responseJson(
								200, 'Application retrieved successfully', [
								'id'      => $application->id,
								'job'     => $application->Job,
								'company' => $application->job->company,
								'status'  => StatusResource::collection(
									 $application->statuses
								),
						  ]
						  );
						  
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Error', 'Application not found');
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, 'Server Error', $errorMessage);
					}
			 }
			 
			 public function destroy($applicationId): JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth()->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $admin = auth('admin')->user();
						  
						  // Check authorization
						  if (!$admin->hasPermissionTo('manage-applications')) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  $application = Application::find($applicationId);
						  if (!$application) {
								 return responseJson(
									  404, 'error', 'Application not found'
								 );
						  }
						  // Delete the application
						  $application->delete();
						  
						  return responseJson(
								200, 'Application deleted successfully'
						  );
						  
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, 'Server Error', $errorMessage);
					}
			 }
	  }