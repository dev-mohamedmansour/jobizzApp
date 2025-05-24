<?php
	  
	  namespace App\Http\Controllers\Auth;
	  
	  use App\Http\Controllers\Controller;
	  use App\Http\Resources\JobListingResource;
	  use App\Models\JobListing;
	  use App\Models\Profile;
	  use Carbon\Carbon;
	  use Exception;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Cache;
	  use Illuminate\Support\Facades\Log;
	  use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
	  
	  class FavoriteController extends Controller
	  {
			 /**
			  * Store or remove a job as a favorite for the authenticated user's profile.
			  */
			 public function store(Request $request, int $profileId, int $jobId = null): JsonResponse
			 {
					try {
						  $user = JWTAuth::user();
						  if (!$user) {
								 return responseJson(401, 'Unauthorized', 'Unauthorized');
						  }
						  
						  $profile = Profile::where('user_id', $user->id)->findOrFail($profileId);
						  
						  // Clear all favorites if no jobId is provided
						  if ($jobId === null) {
								 $profile->favorites()->detach();
								 $this->invalidateCaches($user->id, $profileId, $profile->title_job);
								 return responseJson(200, 'All jobs removed from favorites', 'Success');
						  }
						  
						  $job = JobListing::findOrFail($jobId);
						  
						  $response = $profile->favorites()->toggle($jobId);
						  $this->invalidateCaches($user->id, $profileId, $profile->title_job);
						  
						  $isAdded = !empty($response['attached']);
						  $message = $isAdded ? 'Job added to favorites' : 'Job removed from favorites';
						  $status = $isAdded ? 201 : 200;
						  
						  $data = $isAdded ? [
								'profile_id' => $profileId,
								'job_id' => $jobId,
								'created_at' => Carbon::now()->format('Y-m-d'),
								'updated_at' => Carbon::now()->format('Y-m-d'),
						  ] : $message;
						  
						  return responseJson($status, $message, $data);
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Profile or job not found');
					} catch (Exception $e) {
						  Log::error('Error storing favorite: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Something went wrong');
					}
			 }
			 
			 /**
			  * List all favorite jobs for the authenticated user's profile.
			  */
			 public function index(Request $request, int $profileId): JsonResponse
			 {
					try {
						  $user = JWTAuth::user();
						  if (!$user) {
								 return responseJson(401, 'Unauthorized', 'Unauthorized');
						  }
						  
						  $profile = Profile::where('user_id', $user->id)->findOrFail($profileId);
						  
						  $cacheKey = "profile_favorites_{$profileId}";
						  $favorites = Cache::store('redis')->remember($cacheKey, now()->addMinutes(10), function () use ($profile) {
								 return JobListingResource::collection(
									  $profile->favorites()
											->select([
												 'job_listings.id',
												 'job_listings.company_id',
												 'job_listings.title',
												 'job_listings.job_type',
												 'job_listings.salary',
												 'job_listings.description',
												 'job_listings.requirement',
												 'job_listings.job_status',
												 'job_listings.location',
												 'job_listings.position',
												 'job_listings.benefits',
												 'job_listings.category_name',
											])
											->with(['company' => fn($query) => $query->select(['id', 'name', 'logo'])])
											->get()
								 );
						  });
						  
						  if ($favorites->isEmpty()) {
								 return responseJson(404, 'Not found', 'No favorite jobs found for the profile');
						  }
						  
						  return responseJson(200, 'Favorite jobs retrieved', [
								'favorites' => $favorites,
								'countFavourites' => $favorites->count(),
						  ]);
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Profile not found');
					} catch (Exception $e) {
						  Log::error('Error retrieving favorite jobs: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Something went wrong');
					}
			 }
			 
			 /**
			  * Invalidate relevant caches.
			  */
			 private function invalidateCaches(int $userId, int $profileId, string $profileJobTitle): void
			 {
					Cache::store('redis')->forget("profile_favorites_{$profileId}");
					Cache::store('redis')->forget("jobs_trending_{$userId}");
					Cache::store('redis')->forget("jobs_popular_{$userId}");
					Cache::store('redis')->forget("jobs_recommended_{$userId}_" . md5($profileJobTitle));
			 }
	  }