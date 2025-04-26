<?php
	  
	  namespace App\Http\Controllers\Auth;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Document;
	  use App\Models\DocumentImage;
	  use App\Models\Education;
	  use App\Models\Experience;
	  use App\Models\Profile;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Storage;
	  use Illuminate\Support\Facades\Validator;
	  
	  // Add this line
	  
	  class ProfileController extends Controller
	  {
			 public function getAllProfiles(Request $request): JsonResponse
			 {
					try {
						  $user = $request->user();
						  $profiles = $user->profiles()->with(
								['educations', 'experiences', 'cvs',
								 'portfolios']
						  )->get();
						  if ($profiles->isEmpty()) {
								 return responseJson(
									  200,
									  'You don\'t have any profiles yet. Please add a profile.'
								 );
						  }
						  
						  // Transform each profile to include messages about empty relationships
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
								 
								 return [
									  'profile'  => $profile,
									  'messages' => $messages
								 ];
						  });
						  
						  return responseJson(
								200, 'Profiles retrieved successfully', [
									 'profiles'      => $transformedProfiles,
									 'profile_count' => $profiles->count()
								]
						  );
						  
					} catch (\Exception $e) {
						  $message = config('app.debug')
								? 'Failed to retrieve profiles: ' . $e->getMessage()
								: 'Failed to retrieve profiles. Please try again later';
						  
						  return responseJson(500, $message);
					}
			 }
			 
			 public function getProfileById(Request $request, $id
			 ): JsonResponse {
					try {
						  $profile = Profile::findOrFail($id);
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(403, 'Unauthorized access');
						  }
						  
						  return responseJson(
								200, 'Profile retrieved successfully',
								$profile->load(
									 ['educations', 'experiences', 'documents']
								)
						  );
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Profile not found');
					} catch (\Exception $e) {
						  $message = config('app.debug')
								? 'Server error: ' . $e->getMessage()
								: 'Server error. Please try again later';
						  
						  return responseJson(500, $message);
					}
			 }
			 
			 public function addProfile(Request $request
			 ): JsonResponse {
					$user = $request->user();
					$validator = Validator::make($request->all(), [
						 'title_job'     => 'required|string|max:255',
						 'job_position'  => 'required|string|max:255',
						 'is_default'    => 'sometimes|boolean',
						 'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
					]);
					
					// Check if profile with same title_job already exists for this user
					$existingProfile = $user->profiles()
						 ->where('title_job', $request->title_job)
						 ->first();
					
					if ($existingProfile) {
						  return responseJson(
								409,
								'Profile already exists with the same job title',
						  ); // 409 Conflict status code
					}
					$validatedData = $validator->validated(
					); // Get validated data as array
					
					if ($request->hasFile('profile_image')) {
						  $validatedData['profile_image'] = $request->file(
								'profile_image'
						  )
								->store('profiles', 'public');
					} else {
						  // Set default image URL
						  $validatedData['profile_image']
								= 'https://jobizaa.com/still_images/userDefault.jpg';
					}
					
					if ($validator->fails()) {
						  return responseJson(422, $validator->errors()->first());
					}
					if ($request->title_job) {
					
					}
					// If setting as default, remove default from other profiles
					if ($request->is_default) {
						  $user->profiles()->update(['is_default' => false]);
					}
					
					$profile = $user->profiles()->create($validatedData);
					
					return responseJson(
						 201,
						 "Add Profile Successful ",
						 [
							  $user->name,
							  $profile
						 ]
					);
			 }
			 
			 public function updateProfile(Request $request, $id
			 ): JsonResponse {
					try {
						  // Find the profile or fail
						  $profile = Profile::findOrFail($id);
						  
						  // Manual authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(
									  403,
									  'Unauthorized - You cannot update this profile'
								 );
						  }
						  
						  $validator = Validator::make($request->all(), [
								'title_job'     => 'sometimes|string|max:255',
								'job_position'  => 'sometimes|string|max:255',
								'is_default'    => 'sometimes|boolean',
								'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
						  
						  ]);
						  
						  if ($validator->fails()) {
								 return responseJson(422, 'Validation failed', [
									  'errors' => $validator->errors()
								 ]);
						  }
						  
						  // Get original data before update
						  $originalData = $profile->only(
								['title', 'job_position', 'is_default', 'profile_image']
						  );
						  $newData = $validator->validated();
						  
						  // Check if any data actually changed
						  $changes = array_diff_assoc($newData, $originalData);
						  
						  if (empty($changes) && !$request->has('is_default')) {
								 return responseJson(200, 'No changes detected', [
									  'profile'   => $profile,
									  'unchanged' => true
								 ]);
						  }

//						  // Handle profile_image upload or removal
//						  if ($request->hasFile('profile_image')) {
//								 if ($profile->profile_image
//									  && Storage::disk('public')->exists(
//											$profile->profile_image
//									  )
//								 ) {
//										Storage::disk('public')->delete(
//											 $profile->profile_image
//										);
//								 }
//								 $imagePath = $request->file('profile_image')->store(
//									  'profiles', 'public'
//								 );
//								 $validator['profile_image'] = $imagePath;
//						  } elseif (isset($validator['profile_image'])
//								&& $validator['profile_image'] === ''
//						  ) {
//								 // If the profile_image is empty, remove the existing profile_image
//								 if ($profile->profile_image
//									  && Storage::disk('public')->exists(
//											$profile->profile_image
//									  )
//								 ) {
//										Storage::disk('public')->delete(
//											 $profile->profile_image
//										);
//								 }
//								 $validator['profile_image']
//									  = 'https://jobizaa.com/still_images/companyLogoDefault.jpeg';
//						  }
						  
						  // If setting as default, remove default from other profiles
						  if ($request->is_default) {
								 $profile->user->profiles()
									  ->where('id', '!=', $profile->id)
									  ->update(['is_default' => false]);
						  }
						  
						  // Update only changed fields
						  $profile->update($newData);
						  
						  // Get the changed fields for a response message
						  $changedFields = array_keys($changes);
						  $changeMessage = !empty($changedFields)
								? 'Profile updated. Changed: ' . implode(
									 ', ', $changedFields
								)
								: 'Default status updated';
						  
						  return responseJson(200, $changeMessage, [
								'profile' => $profile,
								'changes' => $changes
						  ]);
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Profile not found');
					} catch (\Exception $e) {
						  return responseJson(500, 'Failed to update profile', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 public function deleteProfile(Request $request, $id
			 ): JsonResponse {
					try {
						  // Find the profile or fail
						  $profile = Profile::findOrFail($id);
						  
						  // Manual authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(
									  403,
									  'Unauthorized - You cannot delete this profile'
								 );
						  }
						  
						  // Delete all related records first to maintain data integrity
						  $profile->educations()->delete();
						  $profile->experiences()->delete();
						  $profile->documents()->delete();
						  
						  // Delete the profile
						  $profile->delete();
						  
						  return responseJson(200, 'Profile deleted successfully');
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Profile not found');
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Failed to delete profile', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 // Education Logic
			 public function getAllEducations(Request $request, $profileId
			 ): JsonResponse {
					try {
						  // Check authentication
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  // Find the profile
						  $profile = Profile::findOrFail($profileId);
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(403, 'Unauthorized action');
						  }
						  
						  // Get all educations for the profile
						  $educations = $profile->educations()->get();
						  
						  if ($educations->isEmpty()) {
								 return responseJson(
									  404, 'No Educations found for this profile'
								 );
						  }
						  
						  // Append image URLs if they exist
						  $educations->each(function ($education) {
								 if ($education->image) {
										$education->image_url = Storage::disk('public')
											 ->url($education->image);
								 }
						  });
						  
						  return responseJson(
								200, 'Educations retrieved successfully', [
									 'educations' => $educations,
								]
						  );
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Profile not found');
					} catch (\Exception $e) {
						  return responseJson(500, 'Failed to retrieve educations', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 public function getEducationById(Request $request, $profileId,
				  $educationId
			 ): JsonResponse {
					try {
						  // Check authentication
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  // Find the profile
						  $profile = Profile::findOrFail($profileId);
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(403, 'Unauthorized action');
						  }
						  
						  // Find the education
						  $education = $profile->educations()->where(
								'id', $educationId
						  )->first();
						  
						  if (!$education) {
								 return responseJson(404, 'Education not found');
						  }
						  
						  // Append image URL if it exists
						  if ($education->image) {
								 $education->image_url = Storage::disk('public')->url(
									  $education->image
								 );
						  }
						  
						  return responseJson(
								200, 'Education retrieved successfully', [
									 'education' => $education,
								]
						  );
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Profile not found');
					} catch (\Exception $e) {
						  return responseJson(500, 'Failed to retrieve education', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 public function addEducation(Request $request, $profileId
			 ): JsonResponse {
					try {
						  // Find the profile
						  $profile = Profile::findOrFail($profileId);
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(403, 'Unauthorized action');
						  }
						  
						  $validator = Validator::make($request->all(), [
								'institution'    => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
								'degree'         => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
								'field_of_study' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
								'start_date'     => [
									 'required',
									 'date',
									 'before_or_equal:today',
									 function ($attribute, $value, $fail) use (
										  $profile, $request
									 ) {
											// Check for a duplicate institution + start_date
											$exists = $profile->educations()
												 ->where(
													  'institution', $request->institution
												 )
												 ->where('start_date', $value)
												 ->exists();
											
											if ($exists) {
												  $fail(
														'You already have an education record from this institution with the same start date.'
												  );
											}
									 }
								],
								'end_date'       => 'nullable|date|after:start_date',
								'is_current'     => 'sometimes|boolean',
								'description'    => 'sometimes|string|max:500|regex:/^[a-zA-Z\s]+$/',
								'location'       => 'sometimes|string|max:255|regex:/^[a-zA-Z\s]+$/',
								'image'          => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
						  ]);
						  
						  
						  if ($validator->fails()) {
								 return responseJson(422, 'Validation failed', [
									  'errors' => $validator->errors()
								 ]);
						  }
//
//						  if ($request->hasFile('image')) {
//								 $validator['image'] = $request->file(
//									  'image'
//								 )
//									  ->store('educations', 'public');
//						  } else {
//								 // Set default image URL
//								 $validator['image']
//									  = 'https://jobizaa.com/still_images/education.jpg';
//						  }
						  
						  // Prepare data
						  $educationData = $validator->validated();
						  if ($educationData['is_current'] ?? false) {
								 $educationData['end_date'] = null;
						  }
						  
						  // Create education
						  $education = $profile->educations()->create($educationData);
						  
						  return responseJson(201, 'Education added successfully', [
								'education' => $education
						  ]);
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Profile not found');
					} catch (\Exception $e) {
						  return responseJson(500, 'Failed to add education', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 public function updateEducation(Request $request, $profileId,
				  $educationId
			 ): JsonResponse {
					try {
						  // Find the profile and education
						  $profile = Profile::findOrFail($profileId);
						  $education = Education::findOrFail($educationId);
						  
						  // Verify ownership
						  if ($request->user()->id !== $profile->user_id
								|| $education->profile_id !== $profile->id
						  ) {
								 return responseJson(
									  403,
									  'Unauthorized - You cannot update this education record'
								 );
						  }
						  
						  $validator = Validator::make($request->all(), [
								'institution'    => 'sometimes|string|max:255',
								'degree'         => 'sometimes|string|max:255',
								'field_of_study' => 'sometimes|string|max:255',
								'start_date'     => [
									 'sometimes',
									 'date',
									 'before_or_equal:today'],
								'end_date'       => 'nullable|date|after:start_date',
								'is_current'     => 'sometimes|boolean',
								'description'    => 'nullable|string|max:500',
								'location'       => 'sometimes|string|max:255|regex:/^[a-zA-Z\s]+$/',
								'image'          => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
						  ]);
						  
						  if ($validator->fails()) {
								 return responseJson(422, 'Validation failed', [
									  'errors' => $validator->errors()
								 ]);
						  }
						  
						  // Get original data before update
						  $originalData = $education->only([
								'institution', 'degree', 'field_of_study',
								'start_date', 'end_date', 'is_current', 'description',
								'image', 'location'
						  ]);
						  
						  // Prepare update data
						  $updateData = $validator->validated();
						  
						  // Handle the current education case
						  if ($updateData['is_current'] ?? false) {
								 $updateData['end_date'] = null;
						  }

//						  // Handle logo upload or removal
//						  if ($request->hasFile('image')) {
//								 if ($education->image
//									  && Storage::disk('public')->exists(
//											$education->image
//									  )
//								 ) {
//										Storage::disk('public')->delete(
//											 $education->image
//										);
//								 }
//								 $imagePath = $request->file('image')->store(
//									  'educations', 'public'
//								 );
//								 $validator['image'] = $imagePath;
//						  } elseif (isset($validator['image'])
//								&& $validator['image'] === ''
//						  ) {
//								 // If the image is empty, remove the existing image
//								 if ($education->image
//									  && Storage::disk('public')->exists(
//											$education->image
//									  )
//								 ) {
//										Storage::disk('public')->delete(
//											 $education->image
//										);
//								 }
//								 $validator['image']
//									  = 'https://jobizaa.com/still_images/education.jpg';
//						  }
						  
						  // Check for actual changes
						  $changes = [];
						  foreach ($updateData as $key => $value) {
								 if ($originalData[$key] != $value) {
										$changes[$key] = [
											 'from' => $originalData[$key],
											 'to'   => $value
										];
								 }
						  }
						  
						  if (empty($changes)) {
								 return responseJson(200, 'No changes detected', [
									  'education' => $education,
									  'changes'   => null,
									  'unchanged' => true
								 ]);
						  }
						  
						  // Perform the update
						  $education->update($updateData);
						  
						  return responseJson(200, 'Education updated successfully', [
								'education' => $education->fresh(),
								'changes'   => $changes,
								'unchanged' => false
						  ]);
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Record not found');
					} catch (\Exception $e) {
						  return responseJson(500, 'Failed to update education', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 public function deleteEducation(Request $request, $profileId,
				  $educationId
			 ): JsonResponse {
					try {
						  // Find the profile and education
						  $profile = Profile::findOrFail($profileId);
						  $education = Education::findOrFail($educationId);
						  
						  // Verify ownership - user must own both profile and education
						  if ($request->user()->id !== $profile->user_id
								|| $education->profile_id !== $profile->id
						  ) {
								 return responseJson(
									  403,
									  'Unauthorized - You cannot delete this education record'
								 );
						  }
						  
						  // Delete the education record
						  if ($education->image
								&& Storage::disk('public')->exists(
									 $education->image
								)
						  ) {
								 Storage::disk('public')->delete($education->image);
						  }
						  
						  $education->delete();
						  
						  return responseJson(200, 'Education deleted successfully', [
								'deleted_education'          => [
									 'id'          => $education->id,
									 'institution' => $education->institution,
									 'degree'      => $education->degree
								],
								'remaining_educations_count' => $profile->educations()
									 ->count()
						  ]);
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Education record not found');
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Failed to delete education', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 // Experience Logic
			 
			 public function getAllExperiences(Request $request, $profileId
			 ): \Illuminate\Http\JsonResponse {
					try {
						  // Check authentication
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  // Find the profile
						  $profile = Profile::findOrFail($profileId);
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(403, 'Unauthorized action');
						  }
						  
						  // Get all experiences for the profile
						  $experiences = $profile->experiences()->get();
						  
						  if ($experiences->isEmpty()) {
								 return responseJson(
									  404, 'No Experiences found for this profile'
								 );
						  }
						  
						  
						  // Append image URLs if they exist
						  $experiences->each(function ($experience) {
								 if ($experience->image) {
										$experience->image_url = Storage::disk('public')
											 ->url($experience->image);
								 }
						  });
						  
						  return responseJson(
								200, 'Experiences retrieved successfully', [
									 'experiences' => $experiences,
								]
						  );
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Profile not found');
					} catch (\Exception $e) {
						  return responseJson(500, 'Failed to retrieve experiences', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 public function getExperienceById(Request $request, $profileId,
				  $experienceId
			 ): \Illuminate\Http\JsonResponse {
					try {
						  // Check authentication
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  // Find the profile
						  $profile = Profile::findOrFail($profileId);
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(403, 'Unauthorized action');
						  }
						  
						  // Find the experience
						  $experience = $profile->experiences()->where(
								'id', $experienceId
						  )->first();
						  
						  if (!$experience) {
								 return responseJson(404, 'Experience not found');
						  }
						  
						  // Append image URL if it exists
						  if ($experience->image) {
								 $experience->image_url = Storage::disk('public')->url(
									  $experience->image
								 );
						  }
						  
						  return responseJson(
								200, 'Experience retrieved successfully', [
									 'experience' => $experience,
								]
						  );
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Profile not found');
					} catch (\Exception $e) {
						  return responseJson(500, 'Failed to retrieve experience', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 public function addExperience(Request $request, $profileId
			 ): JsonResponse {
					try {
						  $profile = Profile::findOrFail($profileId);
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(
									  403,
									  'Unauthorized - You cannot add experiences to this profile'
								 );
						  }
						  
						  $validator = Validator::make($request->all(), [
								'company'     => 'required|string|max:255',
								'position'    => 'required|string|max:255',
								'start_date'  => 'required|date|before_or_equal:today',
								'end_date'    => 'nullable|date|after:start_date|required_if:is_current,false',
								'is_current'  => 'sometimes|boolean',
								'description' => 'nullable|string|max:1000',
								'location'    => 'sometimes|string|max:255|regex:/^[a-zA-Z\s]+$/',
								'image'       => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
						  ]);
						  
						  $existingExperience = $profile->experiences()
								->where('company', $request->company)
								->where('start_date', $request->start_date)
								->first();
						  
						  if ($existingExperience) {
								 return responseJson(
									  409,
									  'company already exists with the same start_date',
								 ); // 409 Conflict status code
						  }
						  
						  
						  if ($validator->fails()) {
								 return responseJson(422, 'Validation failed', [
									  'errors' => $validator->errors()
								 ]);
						  }

//						  if ($request->hasFile('image')) {
//								 $validator['image'] = $request->file(
//									  'image'
//								 )
//									  ->store('experiences', 'public');
//						  } else {
//								 // Set default image URL
//								 $validator['image']
//									  = 'https://jobizaa.com/still_images/experience.jpg';
//						  }
						  
						  // Handle the current job case
						  $experienceData = $validator->validated();
						  if ($experienceData['is_current'] ?? false) {
								 $experienceData['end_date'] = null;
						  }
						  
						  $experience = $profile->experiences()->create(
								$experienceData
						  );
						  
						  return responseJson(201, 'Experience added successfully', [
								'experience' => $experience
						  ]);
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Profile not found');
					} catch (\Exception $e) {
						  return responseJson(500, 'Failed to add experience', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 public function editExperience(Request $request, $profileId,
				  $experienceId
			 ): JsonResponse {
					try {
						  $profile = Profile::findOrFail($profileId);
						  $experience = Experience::findOrFail($experienceId);
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id
								|| $experience->profile_id !== $profile->id
						  ) {
								 return responseJson(
									  403,
									  'Unauthorized - You cannot update this experience'
								 );
						  }
						  
						  $validator = Validator::make($request->all(), [
								'company'     => 'sometimes|string|max:255',
								'position'    => 'sometimes|string|max:255',
								'start_date'  => 'sometimes|date|before_or_equal:today',
								'end_date'    => 'nullable|date|after:start_date|required_if:is_current,false',
								'is_current'  => 'sometimes|boolean',
								'description' => 'nullable|string|max:1000',
								'location'    => 'sometimes|string|max:255|regex:/^[a-zA-Z\s]+$/',
								'image'       => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
						  ]);
						  
						  if ($validator->fails()) {
								 return responseJson(422, 'Validation failed', [
									  'errors' => $validator->errors()
								 ]);
						  }
						  
						  // Get original data
						  $originalData = $experience->only(
								['company', 'position', 'start_date', 'end_date',
								 'is_current', 'description',
								 'image', 'location'
								]
						  );
						  $updateData = $validator->validated();
						  
						  // Handle the current job case
						  if ($updateData['is_current'] ?? false) {
								 $updateData['end_date'] = null;
						  }
						  
						  // Check for changes
						  $changes = [];
						  foreach ($updateData as $key => $value) {
								 if (array_key_exists($key, $originalData)
									  && $originalData[$key] != $value
								 ) {
										$changes[$key] = [
											 'from' => $originalData[$key],
											 'to'   => $value
										];
								 }
						  }
						  
						  if (empty($changes)) {
								 return responseJson(200, 'No changes detected', [
									  'experience' => $experience,
									  'changes'    => null,
									  'unchanged'  => true
								 ]);
						  }

//						  // Handle logo upload or removal
//						  if ($request->hasFile('image')) {
//								 if ($experience->image
//									  && Storage::disk('public')->exists(
//											$experience->image
//									  )
//								 ) {
//										Storage::disk('public')->delete(
//											 $experience->image
//										);
//								 }
//								 $imagePath = $request->file('image')->store(
//									  'experiences', 'public'
//								 );
//								 $validator['image'] = $imagePath;
//						  } elseif (isset($validator['image'])
//								&& $validator['image'] === ''
//						  ) {
//								 // If the image is empty, remove the existing image
//								 if ($experience->image
//									  && Storage::disk('public')->exists(
//											$experience->image
//									  )
//								 ) {
//										Storage::disk('public')->delete(
//											 $experience->image
//										);
//								 }
//								 $validator['image']
//									  = 'https://jobizaa.com/still_images/experience.jpg';
//						  }
						  
						  // Update the experience
						  $experience->update($updateData);
						  
						  return responseJson(
								200, 'Experience updated successfully', [
									 'experience' => $experience->fresh(),
									 'changes'    => $changes,
									 'unchanged'  => false
								]
						  );
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Profile or experience not found');
					} catch (\Exception $e) {
						  return responseJson(500, 'Failed to update experience', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 public function deleteExperience(Request $request, $profileId,
				  $experienceId
			 ): JsonResponse {
					try {
						  $profile = Profile::findOrFail($profileId);
						  $experience = Experience::findOrFail($experienceId);
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id
								|| $experience->profile_id !== $profile->id
						  ) {
								 return responseJson(
									  403,
									  'Unauthorized - You cannot delete this experience'
								 );
						  }
						  
						  // Delete the experience record
						  if ($experience->image
								&& Storage::disk('public')->exists(
									 $experience->image
								)
						  ) {
								 Storage::disk('public')->delete($experience->image);
						  }
						  
						  $experience->delete();
						  
						  return responseJson(
								200, 'Experience deleted successfully', [
									 'deleted_experience'          => [
										  'id'       => $experience->id,
										  'company'  => $experience->company,
										  'position' => $experience->position
									 ],
									 'remaining_experiences_count' => $profile->experiences(
									 )
										  ->count()
								]
						  );
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Profile or experience not found');
					} catch (\Exception $e) {
						  return responseJson(500, 'Failed to delete experience', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 // Document Logic
			 public function uploadCV(Request $request, $profileId
			 ): JsonResponse {
					try {
						  $profile = Profile::findOrFail($profileId);
						  
						  // Authorization
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(403, 'Unauthorized action');
						  }
						  
						  // Check maximum CV limit
						  $currentCVCount = $profile->documents()->where('type', 'cv')
								->count();
						  if ($currentCVCount >= 3) {
								 return responseJson(
									  400, 'Maximum 3 CVs allowed per profile'
								 );
						  }
						  
						  $validator = Validator::make($request->all(), [
								'name' => 'sometimes|string|max:255',
								'file' => 'required|file|mimes:pdf,doc,docx|max:5120'
						  ]);
						  
						  if ($validator->fails()) {
								 return responseJson(422, 'Validation failed', [
									  'errors' => $validator->errors()
								 ]);
						  }
						  
						  $path = $request->file('file')->store('cvs', 'public');
						  
						  $cv = $profile->documents()->create([
								'name'   => $request->name,
								'type'   => 'cv',
								'format' => 'cv',
								'path'   => $path
						  ]);
						  
						  return responseJson(
								201, 'CV uploaded successfully', [
									 'cv'        => $cv,
									 'total_cvs' => $currentCVCount + 1
								]
						  );
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'CV upload failed', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 // Edit CV
			 public
			 function editCV(Request $request, $profileId, $cvId
			 ) {
					try {
						  $profile = Profile::findOrFail($profileId);
						  // Authorization
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(403, 'Unauthorized action');
						  }
						  $cv = $profile->documents()->where('type', 'cv')->find(
								$cvId
						  );
						  
						  if (!$cv) {
								 return responseJson(
									  404,
									  'CV not found or does not belong to this profile'
								 );
						  }
						  
						  $validator = Validator::make($request->all(), [
								'name' => 'sometimes|string|max:255',
								'file' => 'sometimes|file|mimes:pdf,doc,docx|max:5120'
						  ]);
						  
						  if ($validator->fails()) {
								 return responseJson(
									  422,
									  $validator->errors()
								 );
						  }
						  
						  $changes = [];
						  $originalPath = $cv->path;
						  
						  if ($request->hasFile('file')) {
								 $newPath = $request->file('file')->store(
									  'cvs', 'public'
								 );
								 $cv->path = $newPath;
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
						  
						  // Delete an old file after successful update
						  if (isset($newPath)) {
								 Storage::disk('public')->delete($originalPath);
						  }
						  
						  return responseJson(200, 'CV updated successfully', [
								'cv'      => $cv,
								'changes' => $changes
						  ]);
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'CV update failed', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 // Delete CV
			 
			 public
			 function deleteCV(Request $request, $profileId, $cvId
			 ) {
					try {
						  $profile = Profile::findOrFail($profileId);
						  $cv = $profile->documents()
								->where('type', 'cv')
								->findOrFail($cvId);
						  
						  // Authorization
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(403, 'Unauthorized action');
						  }
						  
						  $cv->delete();
						  Storage::disk('public')->delete($cv->path);
						  
						  return responseJson(200, 'CV deleted successfully', [
								'remaining_cvs' => $profile->documents()->where(
									 'type', 'cv'
								)->count()
						  ]);
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'CV deletion failed', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 // Portfolio Logic
			 
			 // Upload Portfolio
			 public function addPortfolioTypeImages(Request $request, $profileId
			 ): JsonResponse {
					$validator = Validator::make($request->all(), [
						 'name'     => 'sometimes|string|max:255',
						 'images'   => 'nullable|array|max:12',
						 'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
					], [
						 'images.max' => 'Maximum 12 images allowed',
					]);
					
					if ($validator->fails()) {
						  return responseJson(
								422,
								$validator->errors()
						  );
					}
					
					try {
						  $profile = Profile::findOrFail($profileId);
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(
									  403,
									  'Unauthorized action'
								 );
						  }
						  
						  DB::beginTransaction();
						  
						  $format = $this->determineFormat($request);
						  $existingPortfolio = null;
						  
						  // For image format handling
						  if ($format === 'images') {
								 $existingPortfolio = $profile->portfolios()
									  ->where('format', 'images')
									  ->first();
						  }
						  
						  if ($existingPortfolio) {
								 // Handle existing image portfolio
								 return $this->handleExistingImagePortfolio(
									  $request, $existingPortfolio
								 );
						  }
						  
						  // Create a new portfolio
						  if ($profile->portfolios()->count() >= 3) {
								 throw new \Exception(
									  'Maximum of 3 portfolios allowed'
								 );
						  }
						  
						  if ($profile->portfolios()->where('format', $format)
								->exists()
						  ) {
								 throw new \Exception(
									  'You already have a portfolio images with this format'
								 );
						  }
						  
						  $portfolio = $profile->documents()->create([
								'name'        => $request->name,
								'type'        => 'portfolio',
								'format'      => $format,
								'image_count' => 0
						  ]);
						  
						  $this->handleFiles($request, $portfolio);
						  
						  DB::commit();
						  
						  return responseJson(
								201,
								' Images Portfolio created successfully',
								$portfolio->load('images')
						  );
						  
					} catch (\Exception $e) {
						  DB::rollBack();
						  return responseJson(
								500,
								"Operation failed" . $e->getMessage()
						  );
					}
			 }
			 
			 protected function determineFormat(Request $request): string
			 {
					if ($request->has('images')) {
						  return 'images';
					}
					if ($request->hasFile('pdf')) {
						  return 'pdf';
					}
					if ($request->filled('url')) {
						  return 'url';
					}
					throw new \Exception('No valid portfolio content provided');
			 }
			 
			 protected function handleExistingImagePortfolio(Request $request,
				  Document $portfolio
			 ): JsonResponse {
					$currentCount = $portfolio->image_count;
					$newImages = $request->file('images');
					$newCount = count($newImages);
					
					if (($currentCount + $newCount) > 12) {
						  $remaining = 12 - $currentCount;
						  throw new \Exception(
								"You can only add {$remaining} more images to this portfolio. "
								.
								"Please create a new portfolio with a different format."
						  );
					}
					
					foreach ($newImages as $image) {
						  $path = $image->store('portfolios/images', 'public');
						  $portfolio->images()->create([
								'path'      => $path,
								'mime_type' => $image->getMimeType()
						  ]);
					}
					
					$portfolio->increment('image_count', $newCount);
					DB::commit();
					
					return responseJson(
						 200,
						 'Images added to existing portfolio',
						 $portfolio->fresh(['images'])
					);
			 }
			 
			 protected function handleFiles(Request $request, Document $portfolio
			 ): void {
					switch ($portfolio->format) {
						  case 'images':
								 $this->handleImageUpload($request->images, $portfolio);
								 break;
						  case 'pdf':
								 $this->handlePdfUpload(
									  $request->file('pdf'), $portfolio
								 );
								 break;
						  case 'url':
								 $portfolio->update(['url' => $request->url]);
								 break;
					}
			 }
			 
			 protected function handleImageUpload($images, Document $portfolio
			 ): void {
					$count = count($images);
					$portfolio->update(['image_count' => $count]);
					
					foreach ($images as $image) {
						  $path = $image->store('portfolios/images', 'public');
						  $portfolio->images()->create([
								'path'      => $path,
								'mime_type' => $image->getMimeType()
						  ]);
					}
			 }
			 
			 protected function handlePdfUpload($file, Document $portfolio): void
			 {
					$path = $file->store('portfolios/pdfs', 'public');
					$portfolio->update(['path' => $path]);
			 }
			 
			 public function deletePortfolioImage(Request $request, $profileId,
				  $imageId
			 ): JsonResponse {
					try {
						  // Find the profile
						  $profile = Profile::findOrFail($profileId);
						  
						  // Find the portfolio (document)
						  $portfolio = Document::where('profile_id', $profileId)
								->where('type', 'portfolio')
								->where('format', 'images')
								->firstOrFail();
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(403, 'Unauthorized action');
						  }
						  
						  // Check portfolio format
						  if ($portfolio->format !== 'images') {
								 return responseJson(
									  422, 'This portfolio is not an image portfolio'
								 );
						  }
						  
						  // Find the image
						  $image = DocumentImage::where('id', $imageId)
								->where('document_id', $portfolio->id)
								->firstOrFail();
						  
						  DB::beginTransaction();
						  
						  // Delete the image file from storage
						  if ($image->path
								&& Storage::disk('public')->exists(
									 $image->path
								)
						  ) {
								 Storage::disk('public')->delete($image->path);
						  }
						  
						  // Delete the image record
						  $image->delete();
						  
						  // Decrement the image count in the portfolio
						  $portfolio->decrement('image_count');
						  
						  DB::commit();
						  
						  return responseJson(200, 'Image deleted successfully');
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'image not found');
					} catch (\Exception $e) {
						  DB::rollBack();
						  return responseJson(500, 'Failed to delete image', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 public function addPortfolioTypePdf(Request $request, $profileId
			 ): JsonResponse {
					$validator = Validator::make($request->all(), [
						 'name' => 'sometimes|string|max:255',
						 'pdf'  => 'required|file|mimes:pdf|max:10240',
					], [
						 'pdf.mimes' => 'Only PDF files are allowed',
					]);
					
					if ($validator->fails()) {
						  return responseJson(
								422,
								$validator->errors()
						  );
					}
					
					try {
						  $profile = Profile::findOrFail($profileId);
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(
									  403,
									  'Unauthorized action'
								 );
						  }
						  
						  DB::beginTransaction();
						  
						  $format = $this->determineFormat($request);
						  // Create a new portfolio
						  if ($profile->portfolios()->count() >= 3) {
								 throw new \Exception(
									  'Maximum of 3 portfolios allowed'
								 );
						  }
						  
						  if ($profile->portfolios()->where('format', $format)
								->exists()
						  ) {
								 throw new \Exception(
									  'You already have a portfolio pdf with this format'
								 );
						  }
						  
						  $portfolio = $profile->documents()->create([
								'name'        => $request->name,
								'type'        => 'portfolio',
								'format'      => $format,
								'image_count' => 0
						  ]);
						  
						  $this->handleFiles($request, $portfolio);
						  
						  DB::commit();
						  
						  return responseJson(
								201,
								'Pdf Portfolio created successfully',
								$portfolio->load('images')
						  );
						  
					} catch (\Exception $e) {
						  DB::rollBack();
						  return responseJson(
								500,
								'Operation failed',
								$e->getMessage()
						  );
					}
			 }
			 
			 public function addPortfolioTypeLink(Request $request, $profileId
			 ): JsonResponse {
					$validator = Validator::make($request->all(), [
						 'name' => 'sometimes|string|max:255',
						 'url'  => 'required|url'
					], [
						 'url.url' => 'Invalid URL format'
					]);
					
					if ($validator->fails()) {
						  return responseJson(
								422,
								$validator->errors()
						  );
					}
					
					try {
						  $profile = Profile::findOrFail($profileId);
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(
									  403,
									  'Unauthorized action'
								 );
						  }
						  
						  DB::beginTransaction();
						  
						  $format = $this->determineFormat($request);
						  // Create a new portfolio
						  if ($profile->portfolios()->count() >= 3) {
								 throw new \Exception(
									  'Maximum of 3 portfolios allowed'
								 );
						  }
						  
						  if ($profile->portfolios()->where('format', $format)
								->exists()
						  ) {
								 throw new \Exception(
									  'You already have a portfolio link with this format'
								 );
						  }
						  
						  $portfolio = $profile->documents()->create([
								'name'        => $request->name,
								'type'        => 'portfolio',
								'format'      => $format,
								'image_count' => 0
						  ]);
						  
						  $this->handleFiles($request, $portfolio);
						  
						  DB::commit();
						  
						  return responseJson(
								201,
								' Link Portfolio created successfully',
								$portfolio->load('images')
						  );
						  
					} catch (\Exception $e) {
						  DB::rollBack();
						  return responseJson(
								500,
								'Operation failed' . $e->getMessage()
						  );
					}
			 }
			 
			 // Update Portfolio
			 
			 public function editPortfolioImages(Request $request, $profileId,
				  $portfolioId
			 ): JsonResponse {
					try {
						  $profile = Profile::findOrFail($profileId);
						  $portfolio = $profile->documents()
								->where('type', 'portfolio')
								->findOrFail($portfolioId);
						  
						  if ($portfolio->format !== 'images') {
								 return responseJson(
									  422, 'This portfolio is not an image portfolio'
								 );
						  }
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(
									  403,
									  'Unauthorized action'
								 );
						  }
						  $validationRules = [
								'name' => 'sometimes|string|max:255',
						  ];
						  
						  $validationRules['images'] = 'sometimes|array|max:'
								. (12 - $portfolio->image_count);
						  $validationRules['images.*']
								= 'image|mimes:jpeg,png,jpg,gif|max:2048';
						  $validationRules['deleted_images']
								= 'sometimes|array';
						  $validationRules['deleted_images.*']
								= 'exists:document_images,id';
						  
						  
						  $validator = Validator::make(
								$request->all(), $validationRules, [
									 'images.max' => 'You can only upload :max more images',
								]
						  );
						  
						  if ($validator->fails()) {
								 return responseJson(
									  422,
									  $validator->errors()
								 );
						  }
						  
						  DB::beginTransaction();
						  
						  $changesMade = false;
						  
						  // Update name
						  if ($request->has('name')
								&& $portfolio->name !== $request->name
						  ) {
								 $portfolio->name = $request->name;
								 $changesMade = true;
						  }
						  
						  // Handle format-specific updates
						  $changesMade = $this->handleImageUpdate(
									 $request, $portfolio
								)
								|| $changesMade;
						  
						  if ($changesMade) {
								 $portfolio->save();
								 DB::commit();
								 
								 return responseJson(
									  200,
									  'Images Portfolio updated successfully',
									  $portfolio->fresh(['images'])
								 );
						  }
						  
						  DB::rollBack();
						  return responseJson(
								200,
								'No changes detected'
						  );
						  
					} catch (\Exception $e) {
						  DB::rollBack();
						  return responseJson(
								500,
								'Update failed' . $e->getMessage()
						  );
					}
			 }
			 
			 protected function handleImageUpdate(Request $request,
				  Document $portfolio
			 ): bool {
					$changes = false;
					
					// Add new images
					if ($request->has('images')) {
						  $newImages = $request->file('images');
						  $path = 'portfolios/images';
						  
						  foreach ($newImages as $image) {
								 $path = $image->store($path, 'public');
								 $portfolio->images()->create([
									  'path'      => $path,
									  'mime_type' => $image->getMimeType()
								 ]);
						  }
						  
						  $portfolio->increment('image_count', count($newImages));
						  $changes = true;
					}
					
					// Delete images
					if ($request->has('deleted_images')) {
						  $deletedImages = $portfolio->images()
								->whereIn('id', $request->deleted_images)
								->get();
						  
						  foreach ($deletedImages as $image) {
								 Storage::disk('public')->delete($image->path);
								 $image->delete();
						  }
						  
						  $portfolio->decrement(
								'image_count', count($request->deleted_images)
						  );
						  $changes = true;
					}
					
					return $changes;
			 }
			 
			 public function editPortfolioPdf(Request $request, $profileId,
				  $portfolioId
			 ): JsonResponse {
					try {
						  $profile = Profile::findOrFail($profileId);
						  $portfolio = $profile->documents()
								->where('type', 'portfolio')
								->findOrFail($portfolioId);
						  
						  if ($portfolio->format !== 'pdf') {
								 return responseJson(
									  422, 'This portfolio is not an pdf portfolio'
								 );
						  }
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(
									  403,
									  'Unauthorized action'
								 );
						  }
						  $validationRules = [
								'name' => 'sometimes|string|max:255',
						  ];
						  // Format-specific validation
						  
						  $validationRules['pdf']
								= 'sometimes|file|mimes:pdf|max:10240';
						  
						  $validator = Validator::make(
								$request->all(), $validationRules, [
									 'pdf.mimes' => 'Only PDF files are allowed',
								]
						  );
						  
						  if ($validator->fails()) {
								 return responseJson(
									  422,
									  $validator->errors()
								 );
						  }
						  
						  DB::beginTransaction();
						  
						  $changesMade = false;
						  
						  // Update name
						  if ($request->has('name')
								&& $portfolio->name !== $request->name
						  ) {
								 $portfolio->name = $request->name;
								 $changesMade = true;
						  }
						  
						  // Handle format-specific updates
						  
						  $changesMade = $this->handlePdfUpdate(
									 $request, $portfolio
								)
								|| $changesMade;
						  
						  if ($changesMade) {
								 $portfolio->save();
								 DB::commit();
								 
								 return responseJson(
									  200,
									  'Pdf Portfolio updated successfully',
									  $portfolio
								 );
						  }
						  
						  DB::rollBack();
						  return responseJson(
								200,
								'No changes detected'
						  );
						  
					} catch (\Exception $e) {
						  DB::rollBack();
						  return responseJson(
								500,
								'Update failed' . $e->getMessage()
						  );
					}
			 }
			 
			 protected function handlePdfUpdate(Request $request,
				  Document $portfolio
			 ): bool {
					if (!$request->hasFile('pdf')) {
						  return false;
					}
					
					// Delete old PDF
					if ($portfolio->path) {
						  Storage::disk('public')->delete($portfolio->path);
					}
					
					// Store new PDF
					$path = $request->file('pdf')->store(
						 'portfolios/pdfs', 'public'
					);
					$portfolio->path = $path;
					return true;
			 }
			 
			 public function editPortfolioUrl(Request $request, $profileId,
				  $portfolioId
			 ): JsonResponse {
					try {
						  $profile = Profile::findOrFail($profileId);
						  $portfolio = $profile->documents()
								->where('type', 'portfolio')
								->findOrFail($portfolioId);
						  
						  if ($portfolio->format !== 'url') {
								 return responseJson(
									  422, 'This portfolio is not an url portfolio'
								 );
						  }
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(
									  403,
									  'Unauthorized action'
								 );
						  }
						  $validationRules = [
								'name' => 'sometimes|string|max:255',
						  ];
						  // Format-specific validation
						  
						  $validationRules['url'] = 'sometimes|url';
						  
						  $validator = Validator::make(
								$request->all(), $validationRules, [
									 'url.url' => 'Invalid URL format'
								]
						  );
						  
						  if ($validator->fails()) {
								 return responseJson(
									  422,
									  $validator->errors()
								 );
						  }
						  
						  DB::beginTransaction();
						  
						  $changesMade = false;
						  
						  // Update name
						  if ($request->has('name')
								&& $portfolio->name !== $request->name
						  ) {
								 $portfolio->name = $request->name;
								 $changesMade = true;
						  }
						  
						  // Handle format-specific updates
						  $changesMade = $this->handleUrlUpdate(
									 $request, $portfolio
								)
								|| $changesMade;
						  
						  if ($changesMade) {
								 $portfolio->save();
								 DB::commit();
								 
								 return responseJson(
									  200,
									  'Link Portfolio updated successfully',
									  $portfolio
								 );
						  }
						  
						  DB::rollBack();
						  return responseJson(
								200,
								'No changes detected'
						  );
						  
					} catch (\Exception $e) {
						  DB::rollBack();
						  return responseJson(
								500,
								'Update failed' . $e->getMessage()
						  );
					}
			 }
			 
			 protected function handleUrlUpdate(Request $request,
				  Document $portfolio
			 ): bool {
					if (!$request->has('url')) {
						  return false;
					}
					
					if ($portfolio->url !== $request->url) {
						  $portfolio->url = $request->url;
						  return true;
					}
					
					return false;
			 }
			 
			 public function deletePortfolio(Request $request, $profileId,
				  $portfolioId
			 ): \Illuminate\Http\JsonResponse {
					try {
						  // Find the profile
						  $profile = Profile::findOrFail($profileId);
						  
						  // Find the portfolio
						  $portfolio = Document::where('id', $portfolioId)
								->where('profile_id', $profileId)
								->where('type', 'portfolio')
								->firstOrFail();

						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(403, 'Unauthorized action');
						  }
						  
						  DB::beginTransaction();
						  
						  // Delete associated files
						  if ($portfolio->format === 'images') {
								 // Delete all images related to this portfolio
								 foreach ($portfolio->images as $image) {
										if ($image->path
											 && Storage::disk('public')->exists(
												  $image->path
											 )
										) {
											  Storage::disk('public')->delete(
													$image->path
											  );
										}
										$image->delete();
								 }
						  } elseif ($portfolio->format === 'pdf') {
								 // Delete the PDF file
								 if ($portfolio->path
									  && Storage::disk('public')->exists(
											$portfolio->path
									  )
								 ) {
										Storage::disk('public')->delete($portfolio->path);
								 }
						  }
						  
						  // Delete the portfolio
						  $portfolio->delete();
						  
						  DB::commit();
						  
						  return responseJson(200, 'Portfolio deleted successfully');
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Portfolio not found');
					} catch (\Exception $e) {
						  DB::rollBack();
						  return responseJson(500, 'Failed to delete portfolio', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }


//			 public function deletePortfolio(Request $request, $profileId,
//				  $portfolioId
//			 ): JsonResponse {
//					try {
//						  $profile = Profile::findOrFail($profileId);
//						  $portfolio = $profile->documents()
//								->where('type', 'portfolio')
//								->findOrFail($portfolioId);
//
//						  if (!$portfolio) {
//								 return responseJson(
//									  404, 'This portfolio is not be Found'
//								 );
//						  }
//
//						  // Authorization check
//						  if ($request->user()->id !== $profile->user_id) {
//								 return responseJson(
//									  403,
//									  'Unauthorized action'
//								 );
//						  }
//
//						  DB::transaction(function () use ($portfolio) {
//								 // Delete associated files
//								 switch ($portfolio->format) {
//										case 'images':
//											  $portfolio->images->each(function ($image) {
//													 Storage::disk('public')->delete(
//														  $image->path
//													 );
//													 $image->delete();
//											  });
//											  break;
//
//										case 'pdf':
//											  Storage::disk('public')->delete(
//													$portfolio->path
//											  );
//											  break;
//								 }
//
//								 $portfolio->delete();
//						  });
//
//						  return responseJson(
//								200,
//								'Portfolio deleted successfully'
//						  );
//
//					} catch (\Exception $e) {
//						  return responseJson(
//								500,
//								'Deletion failed , portfolio not found'
//								. $e->getMessage()
//						  );
//					}
//			 }
			 
	  }