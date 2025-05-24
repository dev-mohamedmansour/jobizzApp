<?php
	  
	  namespace App\Http\Controllers\Auth;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Document;
	  use App\Models\DocumentImage;
	  use App\Models\Education;
	  use App\Models\Experience;
	  use App\Models\Profile;
	  use Illuminate\Database\Eloquent\ModelNotFoundException;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Cache;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Storage;
	  use Illuminate\Support\Facades\Validator;
	  use Illuminate\Validation\ValidationException;
	  
	  class ProfileController extends Controller
	  {
			 /**
			  * Retrieve all profiles for the authenticated user.
			  *
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function getAllProfiles(Request $request): JsonResponse
			 {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profiles = Cache::remember(
								'user_profiles_' . $user->id, now()->addMinutes(1),
								fn() => $user->profiles()->with([
									 'educations'  => fn($query) => $query->select(
										  'id', 'profile_id', 'college', 'degree',
										  'field_of_study', 'start_date', 'end_date',
										  'is_current', 'description', 'location'
									 ),
									 'experiences' => fn($query) => $query->select(
										  'id', 'profile_id', 'company', 'position',
										  'start_date', 'end_date', 'is_current',
										  'description', 'location'
									 ),
									 'cvs'         => fn($query) => $query->where(
										  'type', 'cv'
									 )->select(
										  'id', 'profile_id', 'name', 'type', 'path'
									 ),
									 'portfolios'  => fn($query) => $query->where(
										  'type', 'portfolio'
									 )->select(
										  'id', 'profile_id', 'name', 'type', 'format',
										  'image_count', 'path', 'url'
									 ),
									 'applications' => fn($query) => $query->select(
										  'id', 'profile_id', 'status'
									 )->whereIn('status', ['submitted', 'technical-interview', 'reviewed'])
								])->get()
						  );
						  
						  if ($profiles->isEmpty()) {
								 return responseJson(
									  404, 'No profiles found',
									  'You don\'t have any profiles yet. Please add a profile.'
								 );
						  }
						  
						  $transformedProfiles = $profiles->map(function ($profile) {
								 $messages = [];
								 if ($profile->educations->isEmpty()) {
										$messages[] = 'No education details added yet';
								 }
								 if ($profile->experiences->isEmpty()) {
										$messages[] = 'No experience details added yet';
								 }
								 if ($profile->cvs->isEmpty()) {
										$messages[] = 'No CVs uploaded yet';
								 }
								 if ($profile->portfolios->isEmpty()) {
										$messages[] = 'No portfolios uploaded yet';
								 }
								 // Calculate application counts by status
								 $appliedApplications = $profile->applications->where('status', 'submitted')->count();
								 $interviewApplications = $profile->applications->where('status', 'technical-interview')->count();
								 $reviewedApplications = $profile->applications->where('status', 'reviewed')->count();
								 return [
											'id' => $profile->id,
											'user_id' => $profile->user_id,
											'title_job' => $profile->title_job,
											'job_position' => $profile->job_position,
											'is_default' => $profile->is_default,
											'profile_image' => $profile->profile_image,
											'created_at' => $profile->created_at ? $profile->created_at->format('Y-m-d') : null,
											'updated_at' => $profile->updated_at ? $profile->updated_at->format('Y-m-d') : null,
											'applied_applications' => $appliedApplications,
											'interview_applications' => $interviewApplications,
											'reviewed_applications' => $reviewedApplications,
											'educations' => $profile->educations->map(fn ($edu) => [
												 'id' => $edu->id,
												 'college' => $edu->college,
												 'degree' => $edu->degree,
												 'field_of_study' => $edu->field_of_study,
												 'start_date' => $edu->start_date,
												 'end_date' => $edu->end_date,
												 'is_current' => (bool) $edu->is_current,
												 'description' => $edu->description,
												 'location' => $edu->location,
											])->toArray(),
											'experiences' => $profile->experiences->map(fn ($exp) => [
												 'id' => $exp->id,
												 'company' => $exp->company,
												 'position' => $exp->position,
												 'start_date' => $exp->start_date,
												 'end_date' => $exp->end_date,
												 'is_current' => (bool) $exp->is_current,
												 'description' => $exp->description,
												 'location' => $exp->location,
											])->toArray(),
											'cvs' => $profile->cvs->map(fn ($cv) => [
												 'id' => $cv->id,
												 'name' => $cv->name,
												 'type' => $cv->type,
												 'path' => $cv->path,
											])->toArray(),
											'portfolios' => $profile->portfolios->map(fn ($portfolio) => [
												 'id' => $portfolio->id,
												 'name' => $portfolio->name,
												 'type' => $portfolio->type,
												 'format' => $portfolio->format,
												 'image_count' => $portfolio->image_count,
												 'path' => $portfolio->path,
												 'url' => $portfolio->url,
											])->toArray(),
											
									  'messages' => $messages
								 ];
						  });
						  
						  return responseJson(
								200, 'Profiles retrieved successfully', [
									 'profiles' => $transformedProfiles,
									 'profile_count' => $profiles->count()
								]
						  );
					} catch (\Exception $e) {
						  Log::error('Get all profiles error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 public function getSomeDataOfProfiles(Request $request) :JsonResponse
			 {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profiles =$user->profiles()->get();
						  
						  if ($profiles->isEmpty()) {
								 return responseJson(
									  404, 'No profiles found',
									  'You don\'t have any profiles yet. Please add a profile.'
								 );
						  }
						  
						  $transformedProfiles = $profiles->map(function ($profile) {
								 return [
									  'id' => $profile->id,
									  'title_job' => $profile->title_job,
									  'job_position' => $profile->job_position,
									  'is_default' => $profile->is_default,
									  'profile_image' => $profile->profile_image,
									  'created_at' => $profile->created_at ? $profile->created_at->format('Y-m-d') : null,
									  'updated_at' => $profile->updated_at ? $profile->updated_at->format('Y-m-d') : null,
								 ];
						  });
						  
						  return responseJson(
								200, 'Profiles retrieved successfully', [
									 'profiles' => $transformedProfiles,
									 'profile_count' => $profiles->count()
								]
						  );
					} catch (\Exception $e) {
						  Log::error('Get all profiles error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Retrieve a specific profile by ID.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  *
			  * @return JsonResponse
			  */
			 public function getProfileById(Request $request, int $profileId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  $checkProfile=Profile::find($profileId);
						  
						  if ($user->id !== $checkProfile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $profile = Profile::with([
									 'educations'  => fn($query) => $query->select(
										  'id', 'profile_id', 'college', 'degree',
										  'field_of_study', 'start_date', 'end_date',
										  'is_current', 'description', 'location'
									 ),
									 'experiences' => fn($query) => $query->select(
										  'id', 'profile_id', 'company', 'position',
										  'start_date', 'end_date', 'is_current',
										  'description', 'location'
									 ),
									 'documents'   => fn($query) => $query->select(
										  'id', 'profile_id', 'name', 'type', 'format',
										  'path', 'url'
									 ),
									 'applications' => fn($query) => $query->select(
										  'id', 'profile_id', 'status'
									 )->whereIn('status', ['submitted', 'technical-interview', 'reviewed'])
								])->findOrFail($profileId);
						  
						  // Update is_default for profiles
						  DB::transaction(function () use ($user, $profile) {
								 Profile::where('user_id', $user->id)->update(['is_default' => 0]);
								 $profile->is_default = 1;
								 $profile->save();
						  });
						  
						  // Calculate application counts by status
						  $appliedApplications = $profile->applications->where('status', 'submitted')->count();
						  $interviewApplications = $profile->applications->where('status', 'technical-interview')->count();
						  $reviewedApplications = $profile->applications->where('status', 'reviewed')->count();
						  // Filter profile data to include only relevant fields
						  $filteredData = [
								'id' => $profile->id,
								'user_id' => $profile->user_id,
								'title_job' => $profile->title_job,
								'job_position' => $profile->job_position,
								'is_default' => $profile->is_default,
								'profile_image' => $profile->profile_image,
								'applied_applications' => $appliedApplications,
								'interview_applications' => $interviewApplications,
								'reviewed_applications' => $reviewedApplications,
								'created_at' => $profile->created_at ? $profile->created_at->format('Y-m-d') : null,
								'updated_at' => $profile->updated_at ? $profile->updated_at->format('Y-m-d') : null,
								'educations' => $profile->educations->map(fn ($edu) => [
									 'id' => $edu->id,
									 'college' => $edu->college,
									 'degree' => $edu->degree,
									 'field_of_study' => $edu->field_of_study,
									 'start_date' => $edu->start_date,
									 'end_date' => $edu->end_date,
									 'is_current' => (bool) $edu->is_current,
									 'description' => $edu->description,
									 'location' => $edu->location,
								])->toArray(),
								'experiences' => $profile->experiences->map(fn ($exp) => [
									 'id' => $exp->id,
									 'company' => $exp->company,
									 'position' => $exp->position,
									 'start_date' => $exp->start_date,
									 'end_date' => $exp->end_date,
									 'is_current' => (bool) $exp->is_current,
									 'description' => $exp->description,
									 'location' => $exp->location,
								])->toArray(),
								'documents' => $profile->documents->map(fn ($doc) => [
									 'id' => $doc->id,
									 'name' => $doc->name,
									 'type' => $doc->type,
									 'format' => $doc->format,
									 'url' => $doc->url ?? $doc->path, // Prefer url, fallback to a path
								])->toArray(),
						  ];
						  
						  // Sanitize strings for JSON encoding
						  array_walk_recursive($filteredData, function (&$item) {
								 if (is_string($item) && !mb_check_encoding($item, 'UTF-8')) {
										$item = mb_convert_encoding($item, 'UTF-8', 'auto');
								 }
						  });
						  
						  // Log filtered data for debugging
						  Log::info('Filtered profile data', $filteredData);
						  
						  // Clean any stray output
						  ob_start();
						  $response = responseJson(200, 'Profile retrieved successfully', $filteredData);
						  ob_end_clean();
						  return $response;
						  
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Profile not found');
					} catch (\Exception $e) {
						  Log::error('Get profile by ID error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Create a new profile for the authenticated user.
			  *
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function addProfile(Request $request): JsonResponse
			 {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  // Check if user already has two profiles
						  if ($user->profiles()->count() >= 2) {
								 return responseJson(
									  422, 'Validation error',
									  'You cannot add more than two profiles'
								 );
						  }
						  
						  $validated = $this->validateProfile($request, null);
						  
						  if ($user->profiles()->where(
								'title_job', $validated['title_job']
						  )->exists()
						  ) {
								 return responseJson(
									  409, 'Conflict',
									  'Profile already exists with the same job title'
								 );
						  }
						  
						  $validated = $this->handleProfileImageUpload(
								$request, $validated
						  );
						  
						  if ($validated['is_default'] ?? false) {
								 $user->profiles()->update(['is_default' => false]);
						  }
						  
						  $profile = $user->profiles()->create($validated);
						  
						  Cache::forget('user_profiles_' . $user->id);
						  
						  return responseJson(201, 'Profile created successfully', [
								'user_name' => $user->name,
								'profile'   => $profile
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Add profile error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Validate profile data.
			  *
			  * @param Request      $request
			  * @param Profile|null $profile
			  *
			  * @return array
			  * @throws ValidationException
			  */
			 private function validateProfile(Request $request, ?Profile $profile
			 ): array {
					$rules = [
						 'title_job'     => ['required', 'string', 'max:255'],
						 'job_position'  => ['required', 'string', 'max:255'],
						 'is_default'    => ['required', 'boolean'],
						 'profile_image' => ['nullable', 'image',
													'mimes:jpeg,png,jpg,gif', 'max:2048']
					];
					
					if ($profile) {
						  $rules = array_map(
								fn($rule) => str_replace(
									 'required', 'sometimes', $rule
								), $rules
						  );
					}
					
					return Validator::make($request->all(), $rules, [
						 'title_job.max'       => 'Job title cannot exceed 255 characters',
						 'job_position.max'    => 'Job position cannot exceed 255 characters',
						 'profile_image.image' => 'The profile image must be an image',
						 'profile_image.mimes' => 'The profile image must be a file of type: jpeg, png, jpg, gif',
						 'profile_image.max'   => 'The profile image cannot exceed 2MB in size'
					])->validate();
			 }
			 
			 /**
			  * Handle profile image upload.
			  *
			  * @param Request      $request
			  * @param array        $validated
			  * @param Profile|null $profile
			  *
			  * @return array
			  */
			 private function handleProfileImageUpload(Request $request,
				  array $validated, ?Profile $profile = null
			 ): array {
					if ($request->hasFile('profile_image')) {
						  if ($profile && $profile->profile_image
								&& Storage::disk(
									 'public'
								)->exists($this->normalizePath($profile->profile_image))
						  ) {
								 Storage::disk('public')->delete(
									  $this->normalizePath($profile->profile_image)
								 );
						  }
						  $path = $request->file('profile_image')->store(
								'profiles', 'public'
						  );
						  $validated['profile_image'] = Storage::disk('public')->url(
								$path
						  );
					} elseif (!$profile || !$profile->profile_image) {
						  $validated['profile_image']
								= 'https://jobizaa.com/still_images/userDefault.jpg';
					}
					
					return $validated;
			 }
			 
			 /**
			  * Normalize a file path by removing URL prefix.
			  *
			  * @param string $path
			  *
			  * @return string
			  */
			 private function normalizePath(string $path): string
			 {
					return str_replace(Storage::disk('public')->url(''), '', $path);
			 }
			 private function validateUpdateProfile(Request $request, ?Profile $profile
			 ): array {
					$rules = [
						 'title_job'     => ['sometimes', 'string', 'max:255'],
						 'job_position'  => ['sometimes', 'string', 'max:255'],
						 'is_default'    => ['required', 'string','max:1'],
						 'profile_image' => ['nullable', 'image',
													'mimes:jpeg,png,jpg,gif', 'max:2048']
					];
					
//					if ($profile) {
//						  $rules = array_map(
//								fn($rule) => str_replace(
//									 'required', 'sometimes', $rule
//								), $rules
//						  );
//					}
					
					return Validator::make($request->all(), $rules, [
						 'is_default.required'=>'The is_default mut be 1 or 0',
						 'profile_image.image' => 'The profile image must be an image',
						 'profile_image.mimes' => 'The profile image must be a file of type: jpeg, png, jpg, gif',
						 'profile_image.max'   => 'The profile image cannot exceed 2MB in size'
					])->validate();
			 }
			 /**
			  * Update an existing profile.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  *
			  * @return JsonResponse
			  */
			 public function updateProfile(Request $request, int $profileId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(
									  403, 'Forbidden', 'You cannot update this profile'
								 );
						  }
						  
						  $validated = $this->validateUpdateProfile($request, $profile);
						  
						  $validated = $this->handleProfileImageUpload(
								$request, $validated, $profile
						  );
						  
								 // Check the number of profiles and handle is_default logic
								 $profileCount = $user->profiles()->count();
								 if ($profileCount === 1
									  && $profile->id === $profileId
								 ) {
										// Single profile: Force is_default = 1
										$validated['is_default'] = 1;
								 } elseif ($profileCount === 2) {
										// Two profiles: Ensure only one has is_default = 1
										$otherProfile = $user->profiles()->where(
											 'id', '!=', $profileId
										)->first();
										if ($otherProfile
											 && $otherProfile->is_default == 1
											 && ($validated['is_default'] ?? false)
										) {
											  $otherProfile->update([
													'is_default' => 0,
											  ]);
											  // If another profile is default and current is requested as default, set other to 0
											  $validated['is_default'] = 1;
										} elseif ($otherProfile
											 && $validated['is_default'] == 0
											 && $otherProfile->is_default == 0
										) {
											  // If current is set to 0 and other is 0,
											  //force current to 1 to ensure one default
											  $otherProfile->update([
													'is_default' => 1,
											  ]);
											  $validated['is_default'] = 0;
										}
								 }
						  $originalData = $profile->only(array_keys($validated));
						  $changes = array_diff_assoc($validated, $originalData);

						  if (empty($changes)) {
								 return responseJson(200, 'No changes detected', [
									  'profile'   => $profile,
									  'unchanged' => true
								 ]);
						  }
						  
						  // Update profile and is_default in a transaction
						  DB::transaction(function () use ($user, $profile, $validated) {
								 // Update is_default for other profiles if necessary
								 if ($validated['is_default'] ?? false) {
										$user->profiles()->where('id', '!=', $profile->id)->update(['is_default' => 0]);
								 }
								 // Update the profile
								 $profile->update($validated);
						  });
						  
						  // Clear cache
						  Cache::forget('user_profiles_' . $user->id);
						  Cache::forget('profile_' . $profileId);
						  
						  return responseJson(200, 'Profile updated successfully', [
								'profile' => $profile->fresh(),
								'changes' => $changes
						  ]);
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Profile not found');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Update profile error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Delete a profile and its associated resources.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  *
			  * @return JsonResponse
			  */
			 public function deleteProfile(Request $request, int $profileId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(
									  403, 'Forbidden', 'You cannot delete this profile'
								 );
						  }
						  
						  DB::transaction(function () use ($profile) {
								 foreach ($profile->educations as $education) {
										if ($education->image
											 && Storage::disk('public')->exists(
												  $this->normalizePath(
														$education->image
												  )
											 )
										) {
											  Storage::disk('public')->delete(
													$this->normalizePath($education->image)
											  );
										}
								 }
								 $profile->educations()->delete();
								 
								 foreach ($profile->experiences as $experience) {
										if ($experience->image
											 && Storage::disk('public')->exists(
												  $this->normalizePath(
														$experience->image
												  )
											 )
										) {
											  Storage::disk('public')->delete(
													$this->normalizePath($experience->image)
											  );
										}
								 }
								 $profile->experiences()->delete();
								 
								 foreach ($profile->documents as $document) {
										if ($document->path
											 && Storage::disk('public')->exists(
												  $this->normalizePath($document->path)
											 )
										) {
											  Storage::disk('public')->delete(
													$this->normalizePath($document->path)
											  );
										}
								 }
								 $profile->documents()->delete();
								 
								 if ($profile->profile_image
									  && Storage::disk('public')->exists(
											$this->normalizePath(
												 $profile->profile_image
											)
									  )
								 ) {
										Storage::disk('public')->delete(
											 $this->normalizePath($profile->profile_image)
										);
								 }
								 
								 $profile->delete();
						  });
						  
						  Cache::forget('user_profiles_' . $user->id);
						  Cache::forget('profile_' . $profileId);
						  
						  return responseJson(200, 'Profile deleted successfully');
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Profile not found');
					} catch (\Exception $e) {
						  Log::error('Delete profile error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Retrieve all educations for a specific profile.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  *
			  * @return JsonResponse
			  */
			 public function getAllEducations(Request $request, int $profileId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $educations = Cache::remember(
								'profile_educations_' . $profileId,
								now()->addMinutes(15),
								fn() => $profile->educations()->get()
						  );
						  
						  if ($educations->isEmpty()) {
								 return responseJson(
									  404, 'Not found',
									  'No educations found for this profile'
								 );
						  }
						  
						  $educations->each(function ($education) {
								 if ($education->image) {
										$education->image_url = Storage::disk('public')
											 ->url(
												  $this->normalizePath($education->image)
											 );
								 }
						  });
						  
						  return responseJson(
								200, 'Educations retrieved successfully', [
								'educations' => $educations
						  ]
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Profile not found');
					} catch (\Exception $e) {
						  Log::error('Get all educations error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Retrieve a specific education by ID.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $educationId
			  *
			  * @return JsonResponse
			  */
			 public function getEducationById(Request $request, int $profileId,
				  int $educationId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $education = $profile->educations()->findOrFail(
								$educationId
						  );
						  
						  if ($education->image) {
								 $education->image_url = Storage::disk('public')->url(
									  $this->normalizePath($education->image)
								 );
						  }
						  
						  return responseJson(
								200, 'Education retrieved successfully', [
								'education' => $education
						  ]
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Not found', 'Education not found'
						  );
					} catch (\Exception $e) {
						  Log::error(
								'Get education by ID error: ' . $e->getMessage()
						  );
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Add a new education to a profile.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  *
			  * @return JsonResponse
			  */
			 public function addEducation(Request $request, int $profileId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $validated = $this->validateEducation($request, $profile);
						  
						  if ($profile->educations()->where(
								'college', $validated['college']
						  )->where('start_date', $validated['start_date'])->exists()
						  ) {
								 return responseJson(
									  409, 'Conflict',
									  'Education record with the same college and start date already exists'
								 );
						  }
						  
						  $validated = $this->handleEducationImageUpload(
								$request, $validated
						  );
						  
						  if ($validated['is_current'] ?? false) {
								 $validated['end_date'] = null;
						  }
						  
						  $education = $profile->educations()->create($validated);
						  
						  Cache::forget('profile_educations_' . $profileId);
						  
						  return responseJson(201, 'Education added successfully', [
								'education' => $education
						  ]);
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Profile not found');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Add education error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Validate education data.
			  *
			  * @param Request $request
			  * @param Profile $profile
			  * @param bool    $isCreate
			  *
			  * @return array
			  */
			 private function validateEducation(Request $request, Profile $profile,
				  bool $isCreate = true
			 ): array {
					$rules = [
						 'college'        => ['required', 'string', 'max:255',
													 'regex:/^[a-zA-Z\s\']+$/'],
						 'degree'         => ['required', 'string', 'max:255',
													 'regex:/^[a-zA-Z\s\']+$/'],
						 'field_of_study' => ['required', 'string', 'max:255',
													 'regex:/^[a-zA-Z\s\']+$/'],
						 'start_date'     => ['required', 'date',
													 'before_or_equal:today'],
						 'end_date'       => ['nullable', 'date', 'after:start_date'],
						 'is_current'     => ['sometimes', 'boolean'],
						 'description'    => ['nullable', 'string', 'max:500',
													 'regex:/^[a-zA-Z\s]+$/'],
						 'location'       => ['nullable', 'string', 'max:255',
													 'regex:/^[a-zA-Z\s\-]+$/'],
						 'image'          => ['nullable', 'image',
													 'mimes:jpeg,png,jpg,gif,svg',
													 'max:2048']
					];
					
					if (!$isCreate) {
						  $rules = array_map(
								fn($rule) => str_replace(
									 'required', 'sometimes', $rule
								), $rules
						  );
					}
					
					return Validator::make($request->all(), $rules, [
						 'college.max'        => 'College name cannot exceed 255 characters',
						 'degree.max'         => 'Degree cannot exceed 255 characters',
						 'field_of_study.max' => 'Field of study cannot exceed 255 characters',
						 'description.max'    => 'Description cannot exceed 500 characters',
						 'location.max'       => 'Location cannot exceed 255 characters',
						 'image.image'        => 'The image must be an image',
						 'image.mimes'        => 'The image must be a file of type: jpeg, png, jpg, gif, svg',
						 'image.max'          => 'The image cannot exceed 2MB in size'
					])->validate();
			 }
			 
			 /**
			  * Handle education image upload.
			  *
			  * @param Request        $request
			  * @param array          $validated
			  * @param Education|null $education
			  *
			  * @return array
			  */
			 private function handleEducationImageUpload(Request $request,
				  array $validated, ?Education $education = null
			 ): array {
					if ($request->hasFile('image')) {
						  if ($education && $education->image
								&& Storage::disk(
									 'public'
								)->exists($this->normalizePath($education->image))
						  ) {
								 Storage::disk('public')->delete(
									  $this->normalizePath($education->image)
								 );
						  }
						  $path = $request->file('image')->store(
								'educations', 'public'
						  );
						  $validated['image'] = Storage::disk('public')->url($path);
					} elseif (!$education || !$education->image) {
						  $validated['image']
								= 'https://jobizaa.com/still_images/education.jpg';
					}
					
					return $validated;
			 }
			 
			 /**
			  * Update an existing education.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $educationId
			  *
			  * @return JsonResponse
			  */
			 public function updateEducation(Request $request, int $profileId,
				  int $educationId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  $education = Education::findOrFail($educationId);
						  
						  if ($user->id !== $profile->user_id
								|| $education->profile_id !== $profile->id
						  ) {
								 return responseJson(
									  403, 'Forbidden',
									  'You cannot update this education record'
								 );
						  }
						  
						  $validated = $this->validateEducation(
								$request, $profile, false
						  );
						  
						  $validated = $this->handleEducationImageUpload(
								$request, $validated, $education
						  );
						  
						  if ($validated['is_current'] ?? false) {
								 $validated['end_date'] = null;
						  }
						  
						  $originalData = $education->only(array_keys($validated));
						  $changes = array_diff_assoc($validated, $originalData);
						  
						  if (empty($changes)) {
								 return responseJson(200, 'No changes detected', [
									  'education' => $education,
									  'unchanged' => true
								 ]);
						  }
						  
						  $education->update($validated);
						  
						  Cache::forget('profile_educations_' . $profileId);
						  
						  return responseJson(200, 'Education updated successfully', [
								'education' => $education->fresh(),
								'changes'   => $changes
						  ]);
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Not found', 'Education not found'
						  );
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Update education error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Delete an education record.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $educationId
			  *
			  * @return JsonResponse
			  */
			 public function deleteEducation(Request $request, int $profileId,
				  int $educationId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  $education = Education::findOrFail($educationId);
						  
						  if ($user->id !== $profile->user_id
								|| $education->profile_id !== $profile->id
						  ) {
								 return responseJson(
									  403, 'Forbidden',
									  'You cannot delete this education record'
								 );
						  }
						  
						  if ($education->image
								&& Storage::disk('public')->exists(
									 $this->normalizePath($education->image)
								)
						  ) {
								 Storage::disk('public')->delete(
									  $this->normalizePath($education->image)
								 );
						  }
						  
						  $education->delete();
						  
						  Cache::forget('profile_educations_' . $profileId);
						  
						  return responseJson(200, 'Education deleted successfully');
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Not found', 'Education not found'
						  );
					} catch (\Exception $e) {
						  Log::error('Delete education error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Retrieve all experiences for a specific profile.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  *
			  * @return JsonResponse
			  */
			 public function getAllExperiences(Request $request, int $profileId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $experiences = Cache::remember(
								'profile_experiences_' . $profileId,
								now()->addMinutes(15),
								fn() => $profile->experiences()->get()
						  );
						  
						  if ($experiences->isEmpty()) {
								 return responseJson(
									  404, 'Not found',
									  'No experiences found for this profile'
								 );
						  }
						  
						  $experiences->each(function ($experience) {
								 if ($experience->image) {
										$experience->image_url = Storage::disk('public')
											 ->url(
												  $this->normalizePath($experience->image)
											 );
								 }
						  });
						  
						  return responseJson(
								200, 'Experiences retrieved successfully', [
								'experiences' => $experiences
						  ]
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Profile not found');
					} catch (\Exception $e) {
						  Log::error(
								'Get all experiences error: ' . $e->getMessage()
						  );
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Retrieve a specific experience by ID.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $experienceId
			  *
			  * @return JsonResponse
			  */
			 public function getExperienceById(Request $request, int $profileId,
				  int $experienceId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $experience = $profile->experiences()->findOrFail(
								$experienceId
						  );
						  
						  if ($experience->image) {
								 $experience->image_url = Storage::disk('public')->url(
									  $this->normalizePath($experience->image)
								 );
						  }
						  
						  return responseJson(
								200, 'Experience retrieved successfully', [
								'experience' => $experience
						  ]
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Not found', 'Experience not found'
						  );
					} catch (\Exception $e) {
						  Log::error(
								'Get experience by ID error: ' . $e->getMessage()
						  );
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Add a new experience to a profile.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  *
			  * @return JsonResponse
			  */
			 public function addExperience(Request $request, int $profileId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $validated = $this->validateExperience($request);
						  
						  if ($profile->experiences()->where(
								'company', $validated['company']
						  )->where('start_date', $validated['start_date'])->exists()
						  ) {
								 return responseJson(
									  409, 'Conflict',
									  'Experience record with the same company and start date already exists'
								 );
						  }
						  
						  $validated = $this->handleExperienceImageUpload(
								$request, $validated
						  );
						  
						  if ($validated['is_current'] ?? false) {
								 $validated['end_date'] = null;
						  }
						  
						  $experience = $profile->experiences()->create($validated);
						  
						  Cache::forget('profile_experiences_' . $profileId);
						  
						  return responseJson(201, 'Experience added successfully', [
								'experience' => $experience
						  ]);
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Profile not found');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Add experience error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Validate experience data.
			  *
			  * @param Request $request
			  * @param bool    $isCreate
			  *
			  * @return array
			  */
			 private function validateExperience(Request $request,
				  bool $isCreate = true
			 ): array {
					$rules = [
						 'company'     => ['required', 'string', 'max:255'],
						 'position'    => ['required', 'string', 'max:255'],
						 'start_date'  => ['required', 'date',
												 'before_or_equal:today'],
						 'end_date'    => ['nullable', 'date', 'after:start_date',
												 'required_if:is_current,false'],
						 'is_current'  => ['sometimes', 'boolean'],
						 'description' => ['nullable', 'string', 'max:1000'],
						 'location'    => ['nullable', 'string', 'max:255',
												 'regex:/^[a-zA-Z\s\-]+$/'],
						 'image'       => ['nullable', 'image',
												 'mimes:jpeg,png,jpg,gif,svg', 'max:2048']
					];
					
					if (!$isCreate) {
						  $rules = array_map(
								fn($rule) => str_replace(
									 'required', 'sometimes', $rule
								), $rules
						  );
					}
					
					return Validator::make($request->all(), $rules, [
						 'company.max'     => 'Company name cannot exceed 255 characters',
						 'position.max'    => 'Position cannot exceed 255 characters',
						 'description.max' => 'Description cannot exceed 1000 characters',
						 'location.max'    => 'Location cannot exceed 255 characters',
						 'image.image'     => 'The image must be an image',
						 'image.mimes'     => 'The image must be a file of type: jpeg, png, jpg, gif, svg',
						 'image.max'       => 'The image cannot exceed 2MB in size'
					])->validate();
			 }
			 
			 /**
			  * Handle experience image upload.
			  *
			  * @param Request         $request
			  * @param array           $validated
			  * @param Experience|null $experience
			  *
			  * @return array
			  */
			 private function handleExperienceImageUpload(Request $request,
				  array $validated, ?Experience $experience = null
			 ): array {
					if ($request->hasFile('image')) {
						  if ($experience && $experience->image
								&& Storage::disk(
									 'public'
								)->exists($this->normalizePath($experience->image))
						  ) {
								 Storage::disk('public')->delete(
									  $this->normalizePath($experience->image)
								 );
						  }
						  $path = $request->file('image')->store(
								'experiences', 'public'
						  );
						  $validated['image'] = Storage::disk('public')->url($path);
					} elseif (!$experience || !$experience->image) {
						  $validated['image']
								= 'https://jobizaa.com/still_images/experience.png';
					}
					
					return $validated;
			 }
			 
			 /**
			  * Update an existing experience.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $experienceId
			  *
			  * @return JsonResponse
			  */
			 public function editExperience(Request $request, int $profileId,
				  int $experienceId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  $experience = Experience::findOrFail($experienceId);
						  
						  if ($user->id !== $profile->user_id
								|| $experience->profile_id !== $profile->id
						  ) {
								 return responseJson(
									  403, 'Forbidden',
									  'You cannot update this experience'
								 );
						  }
						  
						  $validated = $this->validateExperience($request, false);
						  
						  $validated = $this->handleExperienceImageUpload(
								$request, $validated, $experience
						  );
						  
						  if ($validated['is_current'] ?? false) {
								 $validated['end_date'] = null;
						  }
						  
						  $originalData = $experience->only(array_keys($validated));
						  $changes = array_diff_assoc($validated, $originalData);
						  
						  if (empty($changes)) {
								 return responseJson(200, 'No changes detected', [
									  'experience' => $experience,
									  'unchanged'  => true
								 ]);
						  }
						  
						  $experience->update($validated);
						  
						  Cache::forget('profile_experiences_' . $profileId);
						  
						  return responseJson(
								200, 'Experience updated successfully', [
								'experience' => $experience->fresh(),
								'changes'    => $changes
						  ]
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Not found', 'Experience not found'
						  );
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Update experience error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Delete an experience record.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $experienceId
			  *
			  * @return JsonResponse
			  */
			 public function deleteExperience(Request $request, int $profileId,
				  int $experienceId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  $experience = Experience::findOrFail($experienceId);
						  
						  if ($user->id !== $profile->user_id
								|| $experience->profile_id !== $profile->id
						  ) {
								 return responseJson(
									  403, 'Forbidden',
									  'You cannot delete this experience'
								 );
						  }
						  
						  if ($experience->image
								&& Storage::disk('public')->exists(
									 $this->normalizePath($experience->image)
								)
						  ) {
								 Storage::disk('public')->delete(
									  $this->normalizePath($experience->image)
								 );
						  }
						  
						  $experience->delete();
						  
						  Cache::forget('profile_experiences_' . $profileId);
						  
						  return responseJson(200, 'Experience deleted successfully');
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Not found', 'Experience not found'
						  );
					} catch (\Exception $e) {
						  Log::error('Delete experience error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Upload a CV to a profile.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  *
			  * @return JsonResponse
			  */
			 public function uploadCV(Request $request, int $profileId): JsonResponse
			 {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $currentCVCount = $profile->documents()->where('type', 'cv')
								->count();
						  if ($currentCVCount >= 3) {
								 return responseJson(
									  400, 'Limit exceeded',
									  'Maximum 3 CVs allowed per profile'
								 );
						  }
						  
						  $validated = $this->validateCV($request);
						  
						  $path = $request->file('file')->store('cvs', 'public');
						  $urlPath = Storage::disk('public')->url($path);
						  
						  $cv = $profile->documents()->create([
								'name'   => $validated['name'] ?? 'CV_' . time(),
								'type'   => 'cv',
								'format' => 'cv',
								'path'   => $urlPath
						  ]);
						  
						  Cache::forget('user_profiles_' . $user->id);
						  
						  return responseJson(201, 'CV uploaded successfully', [
								'cv'        => $cv,
								'total_cvs' => $currentCVCount + 1
						  ]);
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Profile not found');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Upload CV error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Validate CV data.
			  *
			  * @param Request $request
			  * @param bool    $isCreate
			  *
			  * @return array
			  * @throws ValidationException
			  */
			 private function validateCV(Request $request, bool $isCreate = true
			 ): array {
					$rules = [
						 'name' => ['nullable', 'string', 'max:255'],
						 'file' => ['required', 'file', 'mimes:pdf,doc,docx',
										'max:5120']
					];
					
					if (!$isCreate) {
						  $rules['file'] = str_replace(
								'required', 'sometimes', $rules['file']
						  );
					}
					
					return Validator::make($request->all(), $rules, [
						 'name.max'   => 'Name cannot exceed 255 characters',
						 'file.mimes' => 'The file must be a file of type: pdf, doc, docx',
						 'file.max'   => 'The file cannot exceed 5MB in size'
					])->validate();
			 }
			 
			 /**
			  * Update an existing CV.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $cvId
			  *
			  * @return JsonResponse
			  */
			 public function editCV(Request $request, int $profileId, int $cvId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $cv = $profile->documents()->where('type', 'cv')
								->findOrFail($cvId);
						  
						  $validated = $this->validateCV($request, false);
						  
						  $changes = [];
						  $originalPath = $cv->path;
						  
						  if ($request->hasFile('file')) {
								 $newPath = $request->file('file')->store(
									  'cvs', 'public'
								 );
								 $cv->path = Storage::disk('public')->url($newPath);
								 $changes[] = 'CV file updated';
						  }
						  
						  if ($request->has('name') && $cv->name !== $request->name) {
								 $cv->name = $request->name;
								 $changes[] = 'Name updated';
						  }
						  
						  if (empty($changes)) {
								 return responseJson(200, 'No changes detected', [
									  'cv'        => $cv,
									  'unchanged' => true
								 ]);
						  }
						  
						  $cv->save();
						  
						  if (isset($newPath)
								&& Storage::disk('public')->exists(
									 $this->normalizePath($originalPath)
								)
						  ) {
								 Storage::disk('public')->delete(
									  $this->normalizePath($originalPath)
								 );
						  }
						  
						  Cache::forget('user_profiles_' . $user->id);
						  
						  return responseJson(200, 'CV updated successfully', [
								'cv'      => $cv,
								'changes' => $changes
						  ]);
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'CV not found');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Edit CV error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Delete a CV.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $cvId
			  *
			  * @return JsonResponse
			  */
			 public function deleteCV(Request $request, int $profileId, int $cvId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $cv = Document::where('profile_id', $profileId)->where(
								'type', 'cv'
						  )->findOrFail($cvId);
						  
						  if ($cv->path
								&& Storage::disk('public')->exists(
									 $this->normalizePath($cv->path)
								)
						  ) {
								 Storage::disk('public')->delete(
									  $this->normalizePath($cv->path)
								 );
						  }
						  
						  $cv->delete();
						  
						  Cache::forget('user_profiles_' . $user->id);
						  
						  return responseJson(200, 'CV deleted successfully');
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'CV not found');
					} catch (\Exception $e) {
						  Log::error('Delete CV error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Add a portfolio with image format.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  *
			  * @return JsonResponse
			  */
			 public function addPortfolioTypeImages(Request $request, int $profileId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $validated = $this->validatePortfolioImages($request);
						  
						  return DB::transaction(
								function () use ($request, $profile, $validated) {
									  if ($profile->portfolios()->count() >= 3) {
											 throw new \Exception(
												  'Maximum of 3 portfolios allowed'
											 );
									  }
									  
									  $format = 'images';
									  if ($profile->portfolios()->where(
											'format', $format
									  )->exists()
									  ) {
											 return $this->handleExistingImagePortfolio(
												  $request, $profile->portfolios()->where(
												  'format', $format
											 )->first()
											 );
									  }
									  
									  $portfolio = $profile->documents()->create([
											'name'        => $validated['name'] ??
												 'Portfolio_' . time(),
											'type'        => 'portfolio',
											'format'      => $format,
											'image_count' => 0
									  ]);
									  
									  $this->handleImageUpload(
											$validated['images'] ?? [], $portfolio
									  );
									  
									  Cache::forget(
											'user_profiles_' . $profile->user_id
									  );
									  
									  return responseJson(
											201, 'Images portfolio created successfully',
											$portfolio->load('images')
									  );
								}
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Profile not found');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error(
								'Add portfolio images error: ' . $e->getMessage()
						  );
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Validate portfolio images data.
			  *
			  * @param Request $request
			  * @param int     $currentImageCount
			  *
			  * @return array
			  * @throws ValidationException
			  */
			 private function validatePortfolioImages(Request $request,
				  int $currentImageCount = 0
			 ): array {
					return Validator::make($request->all(), [
						 'name'             => ['nullable', 'string', 'max:255'],
						 'images'           => ['nullable', 'array',
														'max:' . (12 - $currentImageCount)],
						 'images.*'         => ['image', 'mimes:jpeg,png,jpg,gif',
														'max:2048'],
						 'deleted_images'   => ['sometimes', 'array'],
						 'deleted_images.*' => ['exists:document_images,id']
					], [
						 'name.max'       => 'Name cannot exceed 255 characters',
						 'images.max'     => 'You can only upload ' . (12
									- $currentImageCount) . ' more images',
						 'images.*.mimes' => 'Each image must be a file of type: jpeg, png, jpg, gif',
						 'images.*.max'   => 'Each image cannot exceed 2MB in size'
					])->validate();
			 }
			 
			 /**
			  * Handle existing image portfolio updates.
			  *
			  * @param Request  $request
			  * @param Document $portfolio
			  *
			  * @return JsonResponse
			  * @throws \Exception
			  */
			 private function handleExistingImagePortfolio(Request $request,
				  Document $portfolio
			 ): JsonResponse {
					$currentCount = $portfolio->image_count;
					$newImages = $request->file('images') ?? [];
					$newCount = count($newImages);
					
					if (($currentCount + $newCount) > 12) {
						  $remaining = 12 - $currentCount;
						  throw new \Exception(
								"You can only add {$remaining} more images to this portfolio."
						  );
					}
					
					$this->handleImageUpload($newImages, $portfolio);
					
					Cache::forget('user_profiles_' . $portfolio->profile->user_id);
					
					return responseJson(
						 200, 'Images added to existing portfolio',
						 $portfolio->fresh(['images'])
					);
			 }
			 
			 /**
			  * Handle image uploads for a portfolio.
			  *
			  * @param array    $images
			  * @param Document $portfolio
			  *
			  * @return void
			  */
			 private function handleImageUpload(array $images, Document $portfolio
			 ): void {
					$count = count($images);
					$portfolio->increment('image_count', $count);
					
					foreach ($images as $image) {
						  $path = $image->store('portfolios/images', 'public');
						  $urlPath = Storage::disk('public')->url($path);
						  $portfolio->images()->create([
								'path'      => $urlPath,
								'mime_type' => $image->getMimeType()
						  ]);
					}
			 }
			 
			 /**
			  * Add a portfolio with PDF format.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  *
			  * @return JsonResponse
			  */
			 public function addPortfolioTypePdf(Request $request, int $profileId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $validated = $this->validatePortfolioPdf($request);
						  
						  return DB::transaction(
								function () use ($request, $profile, $validated) {
									  if ($profile->portfolios()->count() >= 3) {
											 throw new \Exception(
												  'Maximum of 3 portfolios allowed'
											 );
									  }
									  
									  $format = 'pdf';
									  if ($profile->portfolios()->where(
											'format', $format
									  )->exists()
									  ) {
											 throw new \Exception(
												  'You already have a portfolio with this format'
											 );
									  }
									  
									  $portfolio = $profile->documents()->create([
											'name'        => $validated['name'] ??
												 'Portfolio_' . time(),
											'type'        => 'portfolio',
											'format'      => $format,
											'image_count' => 0
									  ]);
									  
									  $this->handlePdfUpload(
											$request->file('pdf'), $portfolio
									  );
									  
									  Cache::forget(
											'user_profiles_' . $profile->user_id
									  );
									  
									  return responseJson(
											201, 'PDF portfolio created successfully',
											$portfolio
									  );
								}
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Profile not found');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Add portfolio PDF error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Validate portfolio PDF data.
			  *
			  * @param Request $request
			  * @param bool    $isCreate
			  *
			  * @return array
			  * @throws ValidationException
			  */
			 private function validatePortfolioPdf(Request $request,
				  bool $isCreate = true
			 ): array {
					$rules = [
						 'name' => ['nullable', 'string', 'max:255'],
						 'pdf'  => ['required', 'file', 'mimes:pdf', 'max:10240']
					];
					
					if (!$isCreate) {
						  $rules['pdf'] = str_replace(
								'required', 'sometimes', $rules['pdf']
						  );
					}
					
					return Validator::make($request->all(), $rules, [
						 'name.max'  => 'Name cannot exceed 255 characters',
						 'pdf.mimes' => 'Only PDF files are allowed',
						 'pdf.max'   => 'The PDF cannot exceed 10MB in size'
					])->validate();
			 }
			 
			 /**
			  * Handle PDF upload for a portfolio.
			  *
			  * @param mixed    $file
			  * @param Document $portfolio
			  *
			  * @return void
			  */
			 private function handlePdfUpload(mixed $file, Document $portfolio): void
			 {
					$path = $file->store('portfolios/pdfs', 'public');
					$portfolio->update(
						 ['path' => Storage::disk('public')->url($path)]
					);
			 }
			 
			 /**
			  * Add a portfolio with URL format.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  *
			  * @return JsonResponse
			  */
			 public function addPortfolioTypeLink(Request $request, int $profileId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $validated = $this->validatePortfolioUrl($request);
						  
						  return DB::transaction(
								function () use ($request, $profile, $validated) {
									  if ($profile->portfolios()->count() >= 3) {
											 throw new \Exception(
												  'Maximum of 3 portfolios allowed'
											 );
									  }
									  
									  $format = 'url';
									  if ($profile->portfolios()->where(
											'format', $format
									  )->exists()
									  ) {
											 throw new \Exception(
												  'You already have a portfolio with this format'
											 );
									  }
									  
									  $portfolio = $profile->documents()->create([
											'name'        => $validated['name'] ??
												 'Portfolio_' . time(),
											'type'        => 'portfolio',
											'format'      => $format,
											'image_count' => 0,
											'url'         => $validated['url']
									  ]);
									  
									  Cache::forget(
											'user_profiles_' . $profile->user_id
									  );
									  
									  return responseJson(
											201, 'Link portfolio created successfully',
											$portfolio
									  );
								}
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Profile not found');
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Add portfolio link error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Validate portfolio URL data.
			  *
			  * @param Request $request
			  * @param bool    $isCreate
			  *
			  * @return array
			  * @throws ValidationException
			  */
			 private function validatePortfolioUrl(Request $request,
				  bool $isCreate = true
			 ): array {
					$rules = [
						 'name' => ['nullable', 'string', 'max:255'],
						 'url'  => ['required', 'url']
					];
					
					if (!$isCreate) {
						  $rules['url'] = str_replace(
								'required', 'sometimes', $rules['url']
						  );
					}
					
					return Validator::make($request->all(), $rules, [
						 'name.max' => 'Name cannot exceed 255 characters',
						 'url.url'  => 'Invalid URL format'
					])->validate();
			 }
			 
			 /**
			  * Edit an image-based portfolio.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $portfolioId
			  *
			  * @return JsonResponse
			  */
			 public function editPortfolioImages(Request $request, int $profileId,
				  int $portfolioId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $portfolio = Document::where('profile_id', $profileId)
								->where('type', 'portfolio')->findOrFail($portfolioId);
						  
						  if ($portfolio->format !== 'images') {
								 return responseJson(
									  422, 'Validation error',
									  'This portfolio is not an image portfolio'
								 );
						  }
						  
						  $validated = $this->validatePortfolioImages(
								$request, $portfolio->image_count
						  );
						  
						  return DB::transaction(
								function () use ($request, $portfolio, $validated) {
									  $changesMade = false;
									  
									  if ($request->has('name')
											&& $portfolio->name !== $request->name
									  ) {
											 $portfolio->name = $request->name;
											 $changesMade = true;
									  }
									  
									  if ($request->hasFile('images')) {
											 $this->handleImageUpload(
												  $request->file('images'), $portfolio
											 );
											 $changesMade = true;
									  }
									  
									  if ($request->has('deleted_images')
											&& !empty($request->deleted_images)
									  ) {
											 foreach ($request->deleted_images as $imageId)
											 {
													$image = DocumentImage::where(
														 'id', $imageId
													)->where('document_id', $portfolio->id)
														 ->firstOrFail();
													if ($image->path
														 && Storage::disk(
															  'public'
														 )->exists(
															  $this->normalizePath(
																	$image->path
															  )
														 )
													) {
														  Storage::disk('public')->delete(
																$this->normalizePath(
																	 $image->path
																)
														  );
													}
													$image->delete();
											 }
											 $portfolio->decrement(
												  'image_count',
												  count($request->deleted_images)
											 );
											 $changesMade = true;
									  }
									  
									  if ($changesMade) {
											 $portfolio->save();
											 Cache::forget(
												  'user_profiles_'
												  . $portfolio->profile->user_id
											 );
											 return responseJson(
												  200, 'Portfolio updated successfully',
												  $portfolio->fresh(['images'])
											 );
									  }
									  
									  return responseJson(
											200, 'No changes detected',
											$portfolio->fresh(['images'])
									  );
								}
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Not found', 'Portfolio not found'
						  );
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error(
								'Edit portfolio images error: ' . $e->getMessage()
						  );
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Edit a PDF-based portfolio.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $portfolioId
			  *
			  * @return JsonResponse
			  */
			 public function editPortfolioPdf(Request $request, int $profileId,
				  int $portfolioId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $portfolio = Document::where('profile_id', $profileId)
								->where('type', 'portfolio')->findOrFail($portfolioId);
						  
						  if ($portfolio->format !== 'pdf') {
								 return responseJson(
									  422, 'Validation error',
									  'This portfolio is not a PDF portfolio'
								 );
						  }
						  
						  $validated = $this->validatePortfolioPdf($request, false);
						  
						  return DB::transaction(
								function () use ($request, $portfolio, $validated) {
									  $changesMade = false;
									  
									  if ($request->has('name')
											&& $portfolio->name !== $request->name
									  ) {
											 $portfolio->name = $request->name;
											 $changesMade = true;
									  }
									  
									  if ($request->hasFile('pdf')) {
											 if ($portfolio->path
												  && Storage::disk(
														'public'
												  )->exists(
														$this->normalizePath($portfolio->path)
												  )
											 ) {
													Storage::disk('public')->delete(
														 $this->normalizePath(
															  $portfolio->path
														 )
													);
											 }
											 $path = $request->file('pdf')->store(
												  'portfolios/pdfs', 'public'
											 );
											 $portfolio->path = Storage::disk('public')
												  ->url($path);
											 $changesMade = true;
									  }
									  
									  if ($changesMade) {
											 $portfolio->save();
											 Cache::forget(
												  'user_profiles_'
												  . $portfolio->profile->user_id
											 );
											 return responseJson(
												  200, 'PDF portfolio updated successfully',
												  $portfolio->fresh()
											 );
									  }
									  
									  return responseJson(
											200, 'No changes detected', $portfolio->fresh()
									  );
								}
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Not found', 'Portfolio not found'
						  );
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Edit portfolio PDF error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Edit a URL-based portfolio.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $portfolioId
			  *
			  * @return JsonResponse
			  */
			 public function editPortfolioUrl(Request $request, int $profileId,
				  int $portfolioId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $portfolio = Document::where('profile_id', $profileId)
								->where('type', 'portfolio')->findOrFail($portfolioId);
						  
						  if ($portfolio->format !== 'url') {
								 return responseJson(
									  422, 'Validation error',
									  'This portfolio is not a URL portfolio'
								 );
						  }
						  
						  $validated = $this->validatePortfolioUrl($request, false);
						  
						  return DB::transaction(
								function () use ($request, $portfolio, $validated) {
									  $changesMade = false;
									  
									  if ($request->has('name')
											&& $portfolio->name !== $request->name
									  ) {
											 $portfolio->name = $request->name;
											 $changesMade = true;
									  }
									  
									  if ($request->has('url')
											&& $portfolio->url !== $request->url
									  ) {
											 $portfolio->url = $request->url;
											 $changesMade = true;
									  }
									  
									  if ($changesMade) {
											 $portfolio->save();
											 Cache::forget(
												  'user_profiles_'
												  . $portfolio->profile->user_id
											 );
											 return responseJson(
												  200,
												  'Link portfolio updated successfully',
												  $portfolio->fresh()
											 );
									  }
									  
									  return responseJson(
											200, 'No changes detected', $portfolio->fresh()
									  );
								}
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Not found', 'Portfolio not found'
						  );
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Edit portfolio URL error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Delete a portfolio image.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $imageId
			  *
			  * @return JsonResponse
			  */
			 public function deletePortfolioImage(Request $request, int $profileId,
				  int $imageId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $portfolio = Document::where('profile_id', $profileId)
								->where('type', 'portfolio')->where('format', 'images')
								->firstOrFail();
						  
						  $image = DocumentImage::where('id', $imageId)->where(
								'document_id', $portfolio->id
						  )->firstOrFail();
						  
						  return DB::transaction(
								function () use ($image, $portfolio) {
									  if ($image->path
											&& Storage::disk('public')->exists(
												 $this->normalizePath($image->path)
											)
									  ) {
											 Storage::disk('public')->delete(
												  $this->normalizePath($image->path)
											 );
									  }
									  
									  $image->delete();
									  $portfolio->decrement('image_count');
									  
									  Cache::forget(
											'user_profiles_' . $portfolio->profile->user_id
									  );
									  
									  return responseJson(
											200, 'Image deleted successfully'
									  );
								}
						  );
					} catch (ModelNotFoundException $e) {
						  return responseJson(404, 'Not found', 'Image not found');
					} catch (\Exception $e) {
						  Log::error(
								'Delete portfolio image error: ' . $e->getMessage()
						  );
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Delete a portfolio.
			  *
			  * @param Request $request
			  * @param int     $profileId
			  * @param int     $portfolioId
			  *
			  * @return JsonResponse
			  */
			 public function deletePortfolio(Request $request, int $profileId,
				  int $portfolioId
			 ): JsonResponse {
					try {
						  $user = $request->user();
						  if (!$user) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $profile = Profile::findOrFail($profileId);
						  if ($user->id !== $profile->user_id) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $portfolio = Document::where('id', $portfolioId)->where(
								'profile_id', $profileId
						  )->where('type', 'portfolio')->firstOrFail();
						  
						  return DB::transaction(function () use ($portfolio) {
								 if ($portfolio->format === 'images') {
										foreach ($portfolio->images as $image) {
											  if ($image->path
													&& Storage::disk('public')->exists(
														 $this->normalizePath(
															  $image->path
														 )
													)
											  ) {
													 Storage::disk('public')->delete(
														  $this->normalizePath($image->path)
													 );
											  }
											  $image->delete();
										}
								 } elseif ($portfolio->format === 'pdf'
									  && $portfolio->path
									  && Storage::disk('public')->exists(
											$this->normalizePath($portfolio->path)
									  )
								 ) {
										Storage::disk('public')->delete(
											 $this->normalizePath($portfolio->path)
										);
								 }
								 
								 $portfolio->delete();
								 
								 Cache::forget(
									  'user_profiles_' . $portfolio->profile->user_id
								 );
								 
								 return responseJson(
									  200, 'Portfolio deleted successfully'
								 );
						  });
					} catch (ModelNotFoundException $e) {
						  return responseJson(
								404, 'Not found', 'Portfolio not found'
						  );
					} catch (\Exception $e) {
						  Log::error('Delete portfolio error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
	  }