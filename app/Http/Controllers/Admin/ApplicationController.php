<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Application;
	  use App\Models\ApplicationStatusHistory;
	  use App\Models\JobListing as Job;
	  use App\Models\Profile;
	  use Illuminate\Database\Eloquent\ModelNotFoundException;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Auth;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Validation\ValidationException;
	  
	  class ApplicationController extends Controller
	  {
			 public function store(Request $request, $profileId,$jobId): JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth('api')->check()) {
								 return responseJson(401, 'Unauthenticated','Unauthenticated');
						  }
						  // Validate request data
						  $validated = $request->validate([
								'cover_letter' => 'sometimes|string|max:1000',
								'cv_id' => 'required|integer|exists:documents,id',
						  ]);
						  // Find the profile
						  $profile = Profile::with('documents')->findOrFail($profileId);
						  // Authorization check: Ensure the current user owns this profile
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'This profile does not belong to you.');
						  }
						  // Verify that the CV belongs to this profile
						  $cv = $profile->documents()->where('type', 'cv')
								->where('id', $validated['cv_id'])
								->first();
						  if (!$cv) {
								 return responseJson(404,'Error', 'CV not found or does not belong to this profile');
						  }
						  if (!isset($validated['cover_letter']))
						  {
								 $validated['cover_letter']='no thing';
						  }
						  $job=Job::find($jobId);
						  if (!$job) {
								 return responseJson(404,'Error', 'Job not found');
						  }
						  // Create application
						  $application = $job->applications()->create([
								'profile_id' => $profile->id,
								'cover_letter' => $validated['cover_letter']? :'No thing',
								'resume_path' => $cv->path,
								'status' => 'pending', // Initial status
						  ]);
						  // Record initial status history
						  $application->statuses()->create([
								'status' => 'pending',
						  ]);
						  return responseJson(201, 'Application submitted successfully', [
								'application' => $application,
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->validator->errors()->all());
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Error','Profile or CV not found');
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage() : 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500,'Server Error', $errorMessage);
					}
			 }
			 
			 public function getUserProfileApplications(Request $request, $profileId): JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth('api')->check()) {
								 return responseJson(401, 'Unauthenticated','Unauthenticated');
						  }
						  
						  $user = auth('api')->user();
						  
						  // Find profile with authorization check
						  $profile = Profile::where('id', $profileId)
								->where('user_id', $user->id)
								->first();
						  
						  if (!$profile) {
								 return responseJson(404, 'Error','Profile not found or does not belong to you');
						  }
						  
						  // Get applications with relationships
						  $applications = Application::with([
								'job:id,title,description',
								'job.company:id,name',
								'job.category:id,name',
								'statuses',
						  ])
								->where('profile_id', $profile->id)
								->paginate(10);
						  
						  return responseJson(200, 'Applications retrieved successfully', [
								'applications' => $applications->items(),
								'meta' => [
									 'current_page' => $applications->currentPage(),
									 'total_pages' => $applications->lastPage(),
									 'total_applications' => $applications->total(),
								]
						  ]);
						  
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage() : 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500,'Server Error', $errorMessage);
					}
			 }
			 
			 public function index(): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  
						  // Check authentication and permissions
						  if (!$admin->hasPermissionTo('manage-applications')) {
								 return responseJson(403, 'Forbidden','Not authorized to access this resource');
						  }
						  
						  // Verify admin has a company association
						  if (!$admin->company_id) {
								 return responseJson(403, 'Forbidden','No company associated with this account');
						  }
						  
						  // Get applications for the admin's company
						  $applications = Application::whereHas(
								'job', function ($query) use ($admin) {
								 $query->where('company_id', $admin->company_id);
						  })
								->with('profile', 'job', 'statuses')
								->paginate(15);
						  
						  return responseJson(200, 'Applications retrieved', [
								'applications' => $applications->items(),
								'meta' => [
									 'current_page' => $applications->currentPage(),
									 'total_pages' => $applications->lastPage(),
									 'total_applications' => $applications->total(),
								]
						  ]);
						  
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage() : 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500,'Server Error',$errorMessage);
					}
			 }
			 
			 public function updateStatus(Application $application, Request $request): JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated','Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  
						  // Check authorization
						  if (!$admin->hasPermissionTo('manage-applications')) {
								 return responseJson(403, 'Forbidden','Unauthorized');
						  }
						  
						  // Validate request data
						  $validated = $request->validate([
								'status' => 'required|string|in:submitted,reviewed,accepted,rejected,team-matching,final-hr-interview,technical-interview,screening-interview',
								'feedback' => 'sometimes|string|max:500',
						  ]);
						  
						  // Update application status
						  $application->update([
								'status' => $validated['status'],
						  ]);
						  
						  // Record status history
						  $application->statuses()->create([
								'status' => $validated['status'],
								'feedback' => $validated['feedback'] ?? null,
						  ]);
						  
						  return responseJson(200, 'Application status updated successfully', [
								'application' => $application,
								'current_status' => $validated['status'],
								'history' => $application->statuses,
						  ]);
						  
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->validator->errors()->all());
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage() : 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, 'Server Error',$errorMessage);
					}
			 }
			 
			 public function getStatusHistoryForUser(Request $request, $applicationId): JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated','Unauthenticated');
						  }
						  
						  $user = auth()->user();
						  
						  // Find the application
						  $application = Application::with('statuses', 'job', 'profile')
								->whereHas('profile', function ($query) use ($user) {
									  $query->where('user_id', $user->id);
								})
								->findOrFail($applicationId);
						  
						  return responseJson(200, 'Status history retrieved successfully', [
								'application' => $application,
								'history' => $application->statuses,
						  ]);
						  
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Error','Application not found');
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage() : 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500,'Server Error',$errorMessage);
					}
			 }
			 
			 public function destroy($applicationId): JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated','Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  
						  // Check authorization
						  if (!$admin->hasPermissionTo('manage-applications')) {
								 return responseJson(403, 'Forbidden','Unauthorized');
						  }
						  $application =Application::find($applicationId);
						  if(!$application)
						  {
								 return responseJson(404,'error', 'Application not found');
						  }
						  // Delete the application
						  $application->delete();
						  
						  return responseJson(200, 'Application deleted successfully');
						  
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage() : 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, 'Server Error',$errorMessage);
					}
			 }
	  }