<?php
	  
	  namespace App\Http\Controllers\Auth;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Profile;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Validator;
	  
	  class ProfileController extends Controller
	  {
			 /**
			  * Display a listing of the user's profiles.
			  */
			 public function index(Request $request)
			 {
					$user = $request->user();
					return response()->json(
						 $user->profiles()->with(
							  ['mainImage', 'educations', 'experiences', 'cvs',
								'portfolios']
						 )->get()
					);
			 }
			 
			 /**
			  * Display the specified profile.
			  */
			 public function show(Profile $profile)
			 {
					$this->authorize('view', $profile);
					
					return response()->json($profile->load([
						 'images',
						 'educations',
						 'experiences',
						 'documents'
					]));
			 }
			 
			 /**
			  * Remove the specified profile from storage.
			  */
			 public function destroy(Profile $profile)
			 {
					$this->authorize('delete', $profile);
					
					$profile->delete();
					return response()->json(null, 204);
			 }
			 
			 /**
			  * Upload profile image
			  */
			 public function uploadImage(Request $request, Profile $profile)
			 {
					$this->authorize('update', $profile);
					
					$validator = Validator::make($request->all(), [
						 'image'   => 'required|image|max:2048',
						 'is_main' => 'sometimes|boolean'
					]);
					
					if ($validator->fails()) {
						  return response()->json($validator->errors(), 422);
					}
					
					$path = $request->file('image')->store('profile_images');
					
					// If setting as main, remove main from other images
					if ($request->is_main) {
						  $profile->images()->update(['is_main' => false]);
					}
					
					$image = $profile->images()->create([
						 'path'    => $path,
						 'is_main' => $request->is_main ?? false
					]);
					
					return response()->json($image, 201);
			 }
			 
			 /**
			  * Store a newly created profile in storage.
			  */
			 public function addProfile(Request $request): \Illuminate\Http\JsonResponse
			 {
					$user = $request->user();
					
					$validator = Validator::make($request->all(), [
						 'title'        => 'required|string|max:255',
						 'job_position' => 'required|string|max:255',
						 'is_default'   => 'sometimes|boolean'
					]);
					
					if ($validator->fails()) {
						  return response()->json($validator->errors(), 422);
					}
					
					// If setting as default, remove default from other profiles
					if ($request->is_default) {
						  $user->profiles()->update(['is_default' => false]);
					}
					
					$profile = $user->profiles()->create($validator->validated());
					
					return response()->json($profile, 201);
			 }
			 
			 /**
			  * Update the specified profile in storage.
			  */
			 public function update(Request $request, Profile $profile)
			 {
					$this->authorize('update', $profile);
					
					$validator = Validator::make($request->all(), [
						 'title'        => 'sometimes|string|max:255',
						 'job_position' => 'sometimes|string|max:255',
						 'is_default'   => 'sometimes|boolean'
					]);
					
					if ($validator->fails()) {
						  return response()->json($validator->errors(), 422);
					}
					
					// If setting as default, remove default from other profiles
					if ($request->is_default) {
						  $profile->user->profiles()->where('id', '!=', $profile->id)
								->update(['is_default' => false]);
					}
					
					$profile->update($validator->validated());
					
					return response()->json($profile);
			 }
			 
			 /**
			  * Add education to profile
			  */
			 public function addEducation(Request $request, Profile $profile)
			 {
					$this->authorize('update', $profile);
					
					$validator = Validator::make($request->all(), [
						 'institution'    => 'required|string|max:255',
						 'degree'         => 'required|string|max:255',
						 'field_of_study' => 'required|string|max:255',
						 'start_date'     => 'required|date',
						 'end_date'       => 'nullable|date|after:start_date',
						 'is_current'     => 'sometimes|boolean',
						 'description'    => 'nullable|string'
					]);
					
					if ($validator->fails()) {
						  return response()->json($validator->errors(), 422);
					}
					
					$education = $profile->educations()->create(
						 $validator->validated()
					);
					
					return response()->json($education, 201);
			 }
			 
			 /**
			  * Add experience to profile
			  */
			 public function addExperience(Request $request, Profile $profile)
			 {
					$this->authorize('update', $profile);
					
					$validator = Validator::make($request->all(), [
						 'company'     => 'required|string|max:255',
						 'position'    => 'required|string|max:255',
						 'start_date'  => 'required|date',
						 'end_date'    => 'nullable|date|after:start_date',
						 'is_current'  => 'sometimes|boolean',
						 'description' => 'nullable|string'
					]);
					
					if ($validator->fails()) {
						  return response()->json($validator->errors(), 422);
					}
					
					$experience = $profile->experiences()->create(
						 $validator->validated()
					);
					
					return response()->json($experience, 201);
			 }
			 
			 /**
			  * Upload document to profile (CV, Portfolio, etc.)
			  */
			 public function uploadDocument(Request $request, Profile $profile)
			 {
					$this->authorize('update', $profile);
					
					$validator = Validator::make($request->all(), [
						 'name' => 'required|string|max:255',
						 'file' => 'required_without:url|file|mimes:pdf,doc,docx|max:5120',
						 'url'  => 'required_without:file|url',
						 'type' => 'required|in:cv,portfolio,certificate,other'
					]);
					
					if ($validator->fails()) {
						  return response()->json($validator->errors(), 422);
					}
					
					$data = [
						 'name' => $request->name,
						 'type' => $request->type
					];
					
					if ($request->hasFile('file')) {
						  $data['path'] = $request->file('file')->store(
								'profile_documents'
						  );
					} else {
						  $data['url'] = $request->url;
					}
					
					$document = $profile->documents()->create($data);
					
					return response()->json($document, 201);
			 }
	  }