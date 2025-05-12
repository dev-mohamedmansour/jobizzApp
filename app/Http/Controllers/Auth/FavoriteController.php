<?php
	  
	  namespace App\Http\Controllers\Auth;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Favorite;
	  use App\Models\JobListing;
	  use App\Models\Profile;
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
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $jobId
			  *
			  * @return JsonResponse
			  */
			 public function store(Request $request, int $profileId, int $jobId
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
						  
						  // Check if the job exists
						  $job = JobListing::findOrFail($jobId);
						  
						  // Check if the job is already favorite
						  $existingFavorite = Favorite::where(
								'profile_id', $profileId
						  )
								->where('job_id', $jobId)
								->first();
						  
						  if ($existingFavorite) {
								 return responseJson(
									  409, 'Conflict', 'Job is already favorite'
								 );
						  }
						  
						  // Create the favorite
						  $favorite = Favorite::create([
								'profile_id' => $profileId,
								'job_id'     => $jobId,
						  ]);
						  
						  // Invalidate cache for this profile's favorites
						  Cache::forget("profile_favorites_$profileId");
						  
						  return responseJson(201, 'Job added to favorites', [
								'id'        => $favorite['id'],
								'jobId'     => $favorite['job_id'],
								'profileId' => $favorite['profile_id'],
						  ]);
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
						  (10), function () use ($profile) {
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
						  });
						  
						  // Explicitly convert to array to ensure clean serialization
						  $responseData = $favorites;
						  
						  return responseJson(
								200, 'Favorite jobs retrieved',
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