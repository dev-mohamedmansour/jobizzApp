<?php
	  
	  namespace App\Http\Controllers;
	  
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Auth;
	  use Illuminate\Support\Facades\Cache;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Validation\ValidationException;
	  
	  class FirebasePushController extends Controller
	  {
			 /**
			  * Register or update the FCM token for the authenticated user.
			  */
			 public function registerToken(Request $request): JsonResponse
			 {
					try {
						  $user = Auth::guard('api')->user();
						  if (!$user) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $validated = $request->validate(
								$this->getValidationRules(),
								$this->getValidationMessages()
						  );
						  
						  // Check if the token has changed to avoid unnecessary updates
						  if ($user->fcm_token === $validated['fcm_token']) {
								 return responseJson(200, 'FCM token unchanged', ['unchanged' => true]);
						  }
						  
						  DB::transaction(function () use ($user, $validated) {
								 $user->update(['fcm_token' => $validated['fcm_token']]);
						  });
						  
						  // Invalidate user-related caches
						  $this->invalidateUserCaches($user->id);
						  
						  Log::info('FCM token registered for user', ['user_id' => $user->id]);
						  
						  return responseJson(200, 'FCM token registered successfully', [
								'user_id' => $user->id,
								'fcm_token' => $validated['fcm_token'],
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (\Exception $e) {
						  Log::error('FCM token registration error: ' . $e->getMessage(), ['user_id' => Auth::id()]);
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to register FCM token');
					}
			 }
			 
			 /**
			  * Get validation rules for FCM token registration.
			  */
			 private function getValidationRules(): array
			 {
					return [
						 'fcm_token' => ['required', 'string', 'max:4096'],
					];
			 }
			 
			 /**
			  * Get validation messages for FCM token registration.
			  */
			 private function getValidationMessages(): array
			 {
					return [
						 'fcm_token.required' => 'The FCM token is required.',
						 'fcm_token.string' => 'The FCM token must be a string.',
						 'fcm_token.max' => 'The FCM token cannot exceed 4096 characters.',
					];
			 }
			 
			 /**
			  * Invalidate user-related caches.
			  */
			 private function invalidateUserCaches(int $userId): void
			 {
					$cache = Cache::store('redis');
					
					// Invalidate user list caches
					$page = 1;
					while ($cache->has("users_page_{$page}")) {
						  $cache->forget("users_page_{$page}");
						  $page++;
					}
					
					// Invalidate profile and application caches
					$profiles = \App\Models\Profile::where('user_id', $userId)->get();
					foreach ($profiles as $profile) {
						  $page = 1;
						  while ($cache->has("applications_profile_{$profile->id}_page_{$page}")) {
								 $cache->forget("applications_profile_{$profile->id}_page_{$page}");
								 $page++;
						  }
						  
						  $applications = $profile->applications;
						  foreach ($applications as $application) {
								 $cache->forget("application_{$application->id}_status_history");
								 
								 $job = $application->job;
								 if ($job) {
										$companyId = $job->company_id;
										$cache->forget("job_{$job->id}_details");
										
										$page = 1;
										while ($cache->has("active_applications_company_{$companyId}_page_{$page}")) {
											  $cache->forget("active_applications_company_{$companyId}_page_{$page}");
											  $page++;
										}
										$page = 1;
										while ($cache->has("rejected_applications_company_{$companyId}_page_{$page}")) {
											  $cache->forget("rejected_applications_company_{$companyId}_page_{$page}");
											  $page++;
										}
										
										$page = 1;
										while ($cache->has("company_{$companyId}_jobs_page_{$page}")) {
											  $cache->forget("company_{$companyId}_jobs_page_{$page}");
											  $page++;
										}
										$cache->forget("company_{$companyId}_details");
										$cache->forget('trending_companies');
										$cache->forget('popular_companies');
								 }
						  }
					}
					
					// Invalidate job recommendation caches for all users
					$userIds = \App\Models\User::pluck('id');
					foreach ($userIds as $otherUserId) {
						  $cache->forget("recommended_jobs_{$otherUserId}");
					}
					
					// Invalidate admin jobs pages
					$page = 1;
					while ($cache->has("admin_jobs_page_{$page}")) {
						  $cache->forget("admin_jobs_page_{$page}");
						  $page++;
					}
			 }
	  }