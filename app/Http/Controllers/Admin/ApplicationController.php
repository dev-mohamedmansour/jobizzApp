<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Application;
	  use App\Models\JobListing as Job;
	  use App\Models\Profile;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Auth;
	  
	  class ApplicationController extends Controller
	  {
			 public function store(Request $request, $profileId, Job $job
			 ): JsonResponse {
					// Check if the user is authenticated
					if (!Auth::check()) {
						  return responseJson(401, 'Unauthorized');
					}
					
					// Validate the request data
					$validated = $request->validate([
						 'cover_letter' => 'required|string|max:1000',
						 'cv_id'        => 'required|integer|exists:documents,id'
					]);
					
					// Find the profile
					$profile = Profile::with('documents')->findOrFail($profileId);
					
					// Authorization check: Ensure the current user owns this profile
					if ($request->user()->id !== $profile->user_id) {
						  return responseJson(
								403,
								'Unauthorized action this profile not allowed'
						  );
					}
					
					// Verify that the CV belongs to this profile
					$cv = $profile->documents()->where('type', 'cv')
						 ->where('id', $validated['cv_id'])
						 ->first();
					
					if (!$cv) {
						  return responseJson(
								404,
								'CV not found or does not belong to this profile'
						  );
					}
					
					try {
						  $application = $job->applications()->create([
								'user_id'      => auth()->id(),
								'cover_letter' => $validated['cover_letter'],
								'resume_path'  => $cv->path
						  ]);
						  
						  return responseJson(
								201,
								'Application submitted successfully',
						  );
						  
					} catch (\Exception $e) {
						  // Catch any unexpected errors
						  return responseJson(
								500,
								'An unexpected error occurred.' . $e->getMessage()
						  );
					}
			 }

			 public function allApplications(Request $request, $profileId)
			 {
					// Check if the user is authenticated
					if (!Auth::check()) {
						  return responseJson(401, 'Unauthorized');
					}
					// Find the profile
					$profile = Profile::findOrFail($profileId);
					// Authorization check: Ensure the current user owns this profile
					if ($request->user()->id !== $profile->user_id) {
						  return responseJson(
								403,
								'Unauthorized action this profile not allowed'
						  );
					}
					
					$applications = Application::where('profile_id', $profileId);
					return responseJson(
						 200,"All Applications",
						 $applications
					);
			 }
			// Admin Application Management
			 public function index(): JsonResponse
			 {
					/** @var \App\Models\Admin $admin */
					$admin = auth('admin')->user();
					// Check authentication and basic permissions
					if (!$admin
						 || (!$admin->hasPermissionTo('manage-applications'))
						 && (!$admin->hasPermissionTo('view-applicant-profiles'))
					) {
						  return responseJson(403, 'Unauthorized');
					}
					// Verify admin has a company association
					if (!$admin->company_id) {
						  return responseJson(
								403, 'No company associated with this account'
						  );
					}
					$applications = Application::whereHas(
						 'job', function ($query) use ($admin) {
						  $query->where('company_id', $admin->company_id);
					}
					)->with('profile', 'job')
						 ->paginate(15);
					return responseJson(
						 200, 'Applications retrieved', $applications
					);
			 }
			 
			 public function updateStatus(Application $application,
				  Request $request
			 ) {
					$this->authorize('manage-application', $application);
					
					$validated = $request->validate([
						 'status'   => 'required|in:reviewed,accepted,rejected',
						 'feedback' => 'sometimes|string|max:500'
					]);
					
					$application->update($validated);
					return responseJson(
						 200, 'Application status updated', $application
					);
			 }
	  }
