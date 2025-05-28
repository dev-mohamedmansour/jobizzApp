<?php
	  
	  namespace App\Http\Controllers\Auth;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\JobListing;
	  use App\Models\Profile;
	  use Carbon\Carbon;
	  use Exception;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
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
						  
						  if (!$job || $job->job_status == 'cancelled') {
								 return responseJson(404, 'Not Found', 'Job not found');
						  }
						  
						  // Toggle favorite
						  $response = $profile->favorites()->toggle($jobId);
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
						  $favorites = $profile->favoriteJobs()
								->select([
									 'job_listings.id',
									 'job_listings.company_id',
									 'job_listings.title',
									 'job_listings.description',
									 'job_listings.job_type',
									 'job_listings.requirement',
									 'job_listings.job_status',
									 'job_listings.position',
									 'job_listings.benefits',
									 'job_listings.category_name',
									 'job_listings.salary',
									 'job_listings.location',
									 'job_listings.updated_at',
									 'job_listings.created_at',
								])
								->with(['company' => function ($query) {
									  $query->select(['id', 'name', 'logo']);
								}])
								->get()
								->map(function ($job) use ($profile) {
									  return [
											'id' => $job->id,
											'title' => $job->title,
											'company_id' => $job->company_id,
											'location' => $job->location,
											'job_type' => $job->job_type,
											'job_status' => $job->job_status,
											'salary' => $job->salary,
											'position' => $job->position,
											'category_name' => $job->category_name,
											'description' => $job->description,
											'requirement' => $job->requirement,
											'benefits' => $job->benefits,
											'isFavorite' => $job->isFavoritedByProfile($profile->id),
											'companyName' => $job->company->name ?? null,
											'companyLogo' => $job->company->logo ?? null,
									  ];
								});
						  
						  return responseJson(
								200,
								'Favorite jobs retrieved',
								[
									 'favorites' => $favorites,
									 'countFavourites' => $favorites->count(),
								]
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