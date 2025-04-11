<?php
	  
	  namespace App\Http\Controllers\Auth;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Document;
	  use App\Models\DocumentImage;
	  use App\Models\Education;
	  use App\Models\Experience;
	  use App\Models\Profile;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Storage;
	  use Illuminate\Support\Facades\Validator;
	  
	  // Add this line
	  
	  class ProfileController extends Controller
	  {
			 
			 public function getAllProfiles(Request $request
			 ): \Illuminate\Http\JsonResponse {
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
						  return responseJson(500, 'Failed to retrieve profiles', [
								'error' => $e->getMessage()
						  ]);
					}
			 }
			 
			 /**
			  * Display the specified profile.
			  */
			 public function getProfileById(Request $request, $id
			 ): \Illuminate\Http\JsonResponse {
					try {
						  $profile = Profile::findOrFail($id);
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(403, 'Unauthorized access');
						  }
						  
						  return responseJson(200, 'Profile retrieved successfully', [
								'profile' => $profile->load(
									 ['educations', 'experiences', 'documents']
								)
						  ]);
						  
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Profile not found');
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 public function addProfile(Request $request
			 ): \Illuminate\Http\JsonResponse {
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
								= 'https://jobizaa.com/images/nonPhoto.jpg';
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
//
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
			 ): \Illuminate\Http\JsonResponse {
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
			 ): \Illuminate\Http\JsonResponse {
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
						  $profile->images()->delete();
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
			 
			 public function addEducation(Request $request, $profileId
			 ): \Illuminate\Http\JsonResponse {
					try {
						  // Find the profile
						  $profile = Profile::findOrFail($profileId);
						  
						  // Authorization check
						  if ($request->user()->id !== $profile->user_id) {
								 return responseJson(403, 'Unauthorized action');
						  }
						  
						  $validator = Validator::make($request->all(), [
								'institution'    => 'required|string|max:255',
								'degree'         => 'required|string|max:255',
								'field_of_study' => 'required|string|max:255',
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
								'description'    => 'nullable|string|max:500'
						  ]);
						  
						  if ($validator->fails()) {
								 return responseJson(422, 'Validation failed', [
									  'errors' => $validator->errors()
								 ]);
						  }
						  
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
			 ): \Illuminate\Http\JsonResponse {
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
								'description'    => 'nullable|string|max:500'
						  ]);
						  
						  if ($validator->fails()) {
								 return responseJson(422, 'Validation failed', [
									  'errors' => $validator->errors()
								 ]);
						  }
						  
						  // Get original data before update
						  $originalData = $education->only([
								'institution', 'degree', 'field_of_study',
								'start_date', 'end_date', 'is_current', 'description'
						  ]);
						  
						  // Prepare update data
						  $updateData = $validator->validated();
						  
						  // Handle the current education case
						  if ($updateData['is_current'] ?? false) {
								 $updateData['end_date'] = null;
						  }
						  
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
			 ): \Illuminate\Http\JsonResponse {
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
			 public function addExperience(Request $request, $profileId
			 ): \Illuminate\Http\JsonResponse {
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
								'description' => 'nullable|string|max:1000'
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
			 ): \Illuminate\Http\JsonResponse {
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
								'description' => 'nullable|string|max:1000'
						  ]);
						  
						  if ($validator->fails()) {
								 return responseJson(422, 'Validation failed', [
									  'errors' => $validator->errors()
								 ]);
						  }
						  
						  // Get original data
						  $originalData = $experience->only(
								['company', 'position', 'start_date', 'end_date',
								 'is_current', 'description']
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
			 ): \Illuminate\Http\JsonResponse {
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
			 ): \Illuminate\Http\JsonResponse {
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
								'name' => 'required|string|max:255',
								'file' => 'required|file|mimes:pdf,doc,docx|max:5120'
						  ]);
						  
						  if ($validator->fails()) {
								 return responseJson(422, 'Validation failed', [
									  'errors' => $validator->errors()
								 ]);
						  }
						  
						  $path = $request->file('file')->store('cvs', 'public');
						  
						  $cv = $profile->documents()->create([
								'name' => $request->name,
								'type' => 'cv',
								'path' => $path
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
			 
			 // Unified Upload/Update Portfolio
			 public function handlePortfolio(Request $request, $profileId,
				  $portfolioId = null
			 ) {
					$validator = Validator::make($request->all(), [
						 'name'             => 'required|string|max:255',
						 'pdf'              => 'nullable|file|mimes:pdf|max:10240',
						 'url'              => 'nullable|url',
						 'images'           => 'nullable|array|max:12',
						 'images.*'         => 'image|mimes:jpeg,png,jpg,gif|max:2048',
						 'deleted_images'   => 'nullable|array',
						 'deleted_images.*' => 'exists:document_images,id'
					]);
					
					if ($validator->fails()) {
						  return responseJson(422, $validator->errors());
					}
					
					$profile = Profile::findOrFail($profileId);
					
					// Authorization check
					if ($request->user()->id !== $profile->user_id) {
						  return responseJson(403, 'Unauthorized');
					}
					
					DB::beginTransaction();
					
					try {
						  $isUpdate = $portfolioId !== null;
						  $portfolio = $isUpdate ? Document::findOrFail($portfolioId)
								: new Document();
						  
						  // Check if creating new portfolio
						  if (!$isUpdate && $profile->portfolios()->count() >= 2) {
								 return responseJson(
									  422,
									  'Maximum 2 portfolios allowed'
								 );
						  }
						  
						  // Determine portfolio format
						  $format = null;
						  if ($request->hasFile('pdf')) {
								 $format = 'pdf';
						  } elseif ($request->filled('url')) {
								 $format = 'url';
						  } elseif ($request->has('images')) {
								 $format = 'images';
						  }
						  
						  if (!$format && !$isUpdate) {
								 return responseJson(
									  422, 'You must provide either PDF, URL, or images'
								 );
						  }
						  
						  // Update portfolio data
						  $portfolioData = [
								'profile_id'  => $profileId,
								'name'        => $request->name,
								'description' => $request->description,
								'type'        => 'portfolio',
								'format'      => $format,
								'max_images'  => 12
						  ];
						  
						  // Handle PDF
						  if ($request->hasFile('pdf')) {
								 // Delete old PDF if exists
								 if ($isUpdate && $portfolio->path) {
										Storage::disk('public')->delete($portfolio->path);
								 }
								 
								 $file = $request->file('pdf');
								 $path = $file->store('portfolios/pdfs', 'public');
								 $portfolioData['path'] = $path;
								 $portfolioData['url'] = Storage::disk('public')->url(
									  $path
								 );
						  }
						  
						  // Handle URL
						  if ($request->filled('url')) {
								 $portfolioData['url'] = $request->url;
								 $portfolioData['path'] = null;
						  }
						  
						  // Save portfolio
						  if ($isUpdate) {
								 $portfolio->update($portfolioData);
						  } else {
								 $portfolio = $profile->documents()->create(
									  $portfolioData
								 );
						  }
						  
						  // Handle Images
						  if ($request->has('images')) {
								 // Check image limit
								 $currentImages = $portfolio->images()->count();
								 $newImagesCount = count($request->images);
								 $remainingSlots = $portfolio->max_images
									  - $currentImages;
								 
								 if ($newImagesCount > $remainingSlots) {
										return responseJson(
											 422,
											 "You can only upload {$remainingSlots} more images (max 12 total)"
										);
								 }
								 
								 // Store new images
								 foreach ($request->images as $image) {
										$path = $image->store(
											 'portfolios/images', 'public'
										);
										
										$portfolio->images()->create([
											 'path'      => $path,
											 'mime_type' => $image->getMimeType(),
											 'url'       => Storage::disk('public')->url(
												  $path
											 )
										]);
								 }
						  }
						  
						  // Handle deleted images
						  if ($request->has('deleted_images')) {
								 $imagesToDelete = DocumentImage::whereIn(
									  'id', $request->deleted_images
								 )
									  ->where('document_id', $portfolio->id)
									  ->get();
								 
								 foreach ($imagesToDelete as $image) {
										Storage::disk('public')->delete($image->path);
										$image->delete();
								 }
						  }
						  
						  DB::commit();
						  
						  $message = $isUpdate
								?
								($this->hasChanges($portfolio, $request)
									 ?
									 'Portfolio updated successfully'
									 :
									 'No changes detected')
								:
								'Portfolio created successfully';
						  
						  return responseJson(
								$isUpdate ? 200 : 201,
								$message,
								$portfolio->fresh()->load('images')
						  );
						  
					} catch (\Exception $e) {
						  DB::rollBack();
						  return responseJson(
								500, 'Portfolio operation failed',
						  );
					}
			 }
			 
			 // Delete Portfolio or specific items
			 
			 private function hasChanges(Document $portfolio, Request $request
			 ): bool {
					return $request->hasFile('pdf') || $request->filled('url')
						 || $request->has('images')
						 || $request->has('deleted_images')
						 || $portfolio->name !== $request->name
						 || $portfolio->description !== $request->description;
			 }
			 
			 // Check if there are actual changes
			 
			 public function deletePortfolio(Request $request, $portfolioId)
			 {
					$portfolio = Document::findOrFail($portfolioId);
					
					// Authorization check
					if ($request->user()->id !== $portfolio->profile->user_id) {
						  return responseJson(
								403, 'Unauthorized'
						  );
					}
					
					DB::beginTransaction();
					
					try {
						  // Delete specific images if requested
						  if ($request->has('delete_images')) {
								 $imagesToDelete = $portfolio->images()
									  ->whereIn('id', $request->delete_images)
									  ->get();
								 
								 foreach ($imagesToDelete as $image) {
										Storage::disk('public')->delete($image->path);
										$image->delete();
								 }
								 
								 DB::commit();
								 return responseJson(
									  200,
									  'Selected images deleted successfully'
//									  $portfolio->fresh()->load('images')
								 );
						  }
						  
						  // Delete entire portfolio
						  if ($portfolio->isPdfPortfolio() && $portfolio->path) {
								 Storage::disk('public')->delete($portfolio->path);
						  } elseif ($portfolio->isImagePortfolio()) {
								 foreach ($portfolio->images as $image) {
										Storage::disk('public')->delete($image->path);
								 }
						  }
						  
						  $portfolio->delete();
						  DB::commit();
						  
						  return responseJson(
								200,
								'Portfolio deleted successfully'
						  );
						  
					} catch (\Exception $e) {
						  DB::rollBack();
						  return responseJson(
								500,
								'Deletion failed'
						  );
					}
			 }
			 
	  }