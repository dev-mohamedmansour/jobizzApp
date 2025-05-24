<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Document;
	  use App\Models\Profile;
	  use App\Models\User;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Cache;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Storage;
	  
	  class UserController extends Controller
	  {
			 /**
			  * Retrieve a paginated list of users for authorized admins.
			  */
			 public function index(Request $request): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  if (!$this->isAdminAuthorized($admin)) {
								 return responseJson(403, 'Forbidden', 'You do not have permission to view users');
						  }
						  
						  $page = $request->get('page', 1);
						  $cacheKey = "users_page_{$page}";
						  $users = Cache::store('redis')->remember($cacheKey, now()->addMinutes(15), fn() =>
						  User::select('id', 'name', 'email', 'created_at', 'updated_at')
								->withCount('profiles')
								->paginate(10)
								->through(fn($user) => $this->mapUserData($user))
						  );
						  
						  if ($users->isEmpty()) {
								 return responseJson(404, 'No users found');
						  }
						  
						  return responseJson(200, 'Users retrieved successfully', [
								'users' => $users,
								'total_count' => $users->total(),
						  ]);
					} catch (\Exception $e) {
						  Log::error('Index users error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve users');
					}
			 }
			 
			 /**
			  * Delete a user and their associated profiles, documents, and related data.
			  */
			 public function destroy(int $id): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  if (!$admin->hasPermissionTo('manage-all-companies')) {
								 return responseJson(403, 'Forbidden', 'You do not have permission to delete this user');
						  }
						  
						  $user = User::findOrFail($id);
						  
						  DB::transaction(function () use ($user) {
								 $profiles = $user->profiles;
								 foreach ($profiles as $profile) {
										// Delete educations and their images
										foreach ($profile->educations as $education) {
											  if ($education->image && Storage::disk('public')->exists($education->image)) {
													 Storage::disk('public')->delete($education->image);
											  }
										}
										$profile->educations()->delete();
										
										// Delete experiences and their images
										foreach ($profile->experiences as $experience) {
											  if ($experience->image && Storage::disk('public')->exists($experience->image)) {
													 Storage::disk('public')->delete($experience->image);
											  }
										}
										$profile->experiences()->delete();
										
										// Delete documents and their files
										foreach ($profile->documents as $document) {
											  if ($document->file && Storage::disk('public')->exists($document->file)) {
													 Storage::disk('public')->delete($document->file);
											  }
										}
										$profile->documents()->delete();
										
										// Delete profile image
										if ($profile->profile_image && Storage::disk('public')->exists($profile->profile_image)) {
											  Storage::disk('public')->delete($profile->profile_image);
										}
										
										// Delete profile applications
										$profile->applications()->delete();
										
										// Delete the profile
										$profile->delete();
								 }
								 
								 // Delete user
								 $user->delete();
						  });
						  
						  // Invalidate caches
						  $this->invalidateUserCaches($id);
						  
						  Log::info("User ID {$id} and associated data deleted successfully");
						  
						  return responseJson(200, 'User and associated data deleted successfully');
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'User not found');
					} catch (\Exception $e) {
						  Log::error('Destroy user error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to delete user');
					}
			 }
			 
			 /**
			  * Check if an admin is authorized to view or manage users.
			  */
			 private function isAdminAuthorized($admin): bool
			 {
					return $admin->hasRole('super-admin') || $admin->hasPermissionTo('manage-all-companies');
			 }
			 
			 /**
			  * Map user data for consistent response format.
			  */
			 private function mapUserData(User $user): array
			 {
					return [
						 'id' => $user->id,
						 'name' => $user->name,
						 'email' => $user->email,
						 'profiles_count' => $user->profiles_count,
						 'created_at' => $user->created_at->toDateTimeString(),
						 'updated_at' => $user->updated_at->toDateTimeString(),
					];
			 }
			 
			 /**
			  * Invalidate user, profile, application, job, and company-related caches.
			  */
			 private function invalidateUserCaches(int $userId): void
			 {
					// Invalidate user list caches
					$page = 1;
					while (Cache::store('redis')->has("users_page_{$page}")) {
						  Cache::store('redis')->forget("users_page_{$page}");
						  $page++;
					}
					
					// Invalidate profile and application caches
					$profiles = Profile::where('user_id', $userId)->get();
					foreach ($profiles as $profile) {
						  $page = 1;
						  while (Cache::store('redis')->has("applications_profile_{$profile->id}_page_{$page}")) {
								 Cache::store('redis')->forget("applications_profile_{$profile->id}_page_{$page}");
								 $page++;
						  }
						  
						  $applications = $profile->applications;
						  foreach ($applications as $application) {
								 Cache::store('redis')->forget("application_{$application->id}_status_history");
								 
								 $job = $application->job;
								 if ($job) {
										$companyId = $job->company_id;
										Cache::store('redis')->forget("job_{$job->id}_details");
										
										// Invalidate company application caches
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
										
										// Invalidate job and company caches
										$page = 1;
										while (Cache::store('redis')->has("company_{$companyId}_jobs_page_{$page}")) {
											  Cache::store('redis')->forget("company_{$companyId}_jobs_page_{$page}");
											  $page++;
										}
										Cache::store('redis')->forget("company_{$companyId}_details");
										Cache::store('redis')->forget('trending_companies');
										Cache::store('redis')->forget('popular_companies');
								 }
						  }
					}
					
					// Invalidate job-related caches for recommended jobs
					$userIds = User::pluck('id');
					foreach ($userIds as $otherUserId) {
						  Cache::store('redis')->forget("recommended_jobs_{$otherUserId}");
					}
					
					// Invalidate admin jobs pages
					$page = 1;
					while (Cache::store('redis')->has("admin_jobs_page_{$page}")) {
						  Cache::store('redis')->forget("admin_jobs_page_{$page}");
						  $page++;
					}
			 }
	  }