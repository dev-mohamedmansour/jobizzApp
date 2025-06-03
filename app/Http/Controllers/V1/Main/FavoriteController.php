<?php
	  
	  namespace App\Http\Controllers\V1\Main;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\JobListing;
	  use App\Models\Profile;
	  use Carbon\Carbon;
	  use Exception;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Cache;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Validation\ValidationException;
	  use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
	  
	  class FavoriteController extends Controller
	  {
			 /**
			  * Store a job as a favorite for the authenticated user.
			  *
			  * @param Request  $request
			  * @param int      $profileId
			  * @param int|null $jobId
			  *
			  * @return JsonResponse
			  */
			 public function store(Request $request, int $profileId,
				  int $jobId = null
			 ): JsonResponse {
					try {
						  // Get the authenticated user
						  $user = JWTAuth::user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthorized', 'Unauthorized'
								 );
						  }
						  
						  // Check if the profile belongs to the user
						  $profile = Profile::findOrFail($profileId);
						  if ($profile->user_id !== $user->id) {
								 return responseJson(
									  403, 'Forbidden',
									  'This profile does not belong to the authenticated user'
								 );
						  }
						  if ($jobId === null) {
								 $profile->favorites()->detach();
								 return responseJson(
									  200, 'Jobs removed from favorites',
									  'All jobs removed from favorites successfully'
								 );
						  }
						  // Check if the job exists
						  $job = JobListing::findOrFail($jobId);
						  
						  if (!$job) {
								 return responseJson(404, 'Not Found', 'Job not found');
						  }
						  
						  // Toggle favorite
						  $response = $profile->favorites()->toggle($jobId);
						  Cache::forget("profile_favorites_{$profileId}");
						  Cache::forget('jobs_trending_' . $user->id);
						  Cache::forget('jobs_popular_' . $user->id);
						  Cache::forget('jobs_recommended_' . $user->id . '_' . md5($profile->title_job));
						  $isAdded = !empty($response['attached']);
						  $message = $isAdded ? 'Job added to favorites'
								: 'Job removed from favorites';
						  $status = $isAdded ? 201 : 200;
						  $data = $isAdded ? $profile->favorites()->find(
								$jobId
						  )->pivot : $message;
						  if ($data == $message) {
								 return responseJson($status, $message, $data);
						  }
						  // Clean up response to remove model metadata
						  $data = $data->toArray();
						  
						  $castData = [
								'profile_id' => $data['profile_id'],
								'job_id'     => $data['job_listing_id'],
								'created_at' => Carbon::parse($data['created_at'])
									 ->format('Y-m-d'),
								'updated_at' => Carbon::parse($data['updated_at'])
									 ->format('Y-m-d'),
						  ];
						  return responseJson(
								$status, $message, $castData
						  );
//						  $responses = $profile->favorites()->toggle($jobId);
//
//						  // Invalidate cache for this profile's favorites
//						  Cache::forget("profile_favorites_$profileId");
//						  if ($responses['detached'] == null) {
//								 $favorite = JobListingProfile::where(
//									  'profile_id', $profileId
//								 )->where('job_listing_id', $jobId)->first();
//								 return responseJson(
//									  201, 'Job added to favorites', $favorite
//								 );
//						  }
//						  return responseJson(
//								200, 'Job removed from favorites',
//								'Job removed from favorites'
//						  );
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (Exception $e) {
						  Log::error('Error storing favorite: ' . $e->getMessage());
						  return responseJson(
								500,
								'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong. Please try again later'
						  );
					}
			 }
			 
			 /**
			  * List all favorite jobs for the authenticated user’s profile.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  *
			  * @return JsonResponse
			  */
			 public function index(Request $request, int $profileId): JsonResponse
			 {
					try {
						  // Get the authenticated user
						  $user = JWTAuth::user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthorized', 'Unauthorized'
								 );
						  }
						  
						  // Check if the profile belongs to the user
						  $profile = Profile::findOrFail($profileId);
						  if ($profile->user_id !== $user->id) {
								 return responseJson(
									  403, 'Forbidden',
									  'This profile does not belong to the authenticated user'
								 );
						  }
						  if ($profile->favoriteJobs()->count() === 0) {
								 return responseJson(
									  404, 'Not Found',
									  'No favorite jobs found for the profile'
								 );
						  }
						  // Cache the favorite jobs for 10 minutes
						  $cacheKey = "profile_favorites_$profileId";
						  $favorites = Cache::remember(
								$cacheKey, now()->addMinutes
						  (
								10
						  ), function () use ($profile) {
								 return $profile->favoriteJobs()
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
									  ->with(['company' => function ($query) {
											 $query->select(['id', 'name', 'logo']);
									  }])
									  ->get();
						  }
						  );
						  
						  // Explicitly convert to array to ensure clean serialization
						  $responseData = $favorites;
						  
						  return responseJson(
								200, 'Favorites jobs retrieved',
								['favorites'       => $responseData,
								 'countFavourites' => count($favorites)]
						  );
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (Exception $e) {
						  Log::error(
								'Error retrieving favorite jobs: ' . $e->getMessage()
						  );
						  return responseJson(
								500,
								'Server error',
								config('app.debug') ? $e->getMessage()
									 : 'Something went wrong. Please try again later'
						  );
					}
			 }
	  }