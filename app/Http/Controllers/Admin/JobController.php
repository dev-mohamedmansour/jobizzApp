<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Admin;
	  use App\Models\JobListing as Job;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Log;
	  
	  class JobController extends Controller
	  {
			 public function index(Request $request)
			 {
					$jobs = Job::with('company', 'categories')
						 ->when($request->category, function ($query) use ($request) {
								$query->whereHas(
									 'categories',
									 fn($q) => $q->where('slug', $request->category)
								);
						 })
						 ->paginate(15);
					
					return responseJson(200, 'Jobs retrieved', $jobs);
			 }
			 
			 public function show(Job $job)
			 {
					return responseJson(200, 'Job details', [
						 'job'          => $job->load('company', 'categories'),
						 'similar_jobs' => Job::whereHas(
							  'categories', fn($q) => $q->whereIn(
							  'id', $job->categories->pluck('id')
						 )
						 )
							  ->where('id', '!=', $job->id)
							  ->limit(5)
							  ->get()
					]);
			 }
//			 public function index()
//			 {
//					/** @var Admin $admin */
//
//					$admin = auth('admin')->user();
//
//					// Check if admin is authenticated
//					if (!$admin) {
//						  return responseJson(401, 'Unauthenticated');
//					}
//
//					// Check permissions using the admin instance
//					if ($admin->hasPermissionTo('manage-all-jobs')) {
//						  $jobs = Job::all();
//					} elseif ($admin->hasPermissionTo('manage-company-jobs')) {
//						  $jobs = Job::where('company_id', $admin->company_id)->get();
//					} else {
//						  return responseJson(403, 'Unauthorized');
//					}
//
//					return responseJson(200, 'Jobs retrieved', $jobs);
//			 }
			 
			 public function store(Request $request)
			 {
					try {
						  $admin = auth('admin')->user();
						  $validationRules = [];
						  $validationCustomMessages = [];
						  
						  if ($admin->hasRole('super-admin')) {
								 $validationRules = [
									  'category'     => 'required|integer|exists:categories,id',
									  /**/
									  'title'          => 'required|string|max:255',
									  'company_id'     => 'required|exists:companies,id',
									  'job_type'       => 'required|string|in:Full-time,Part-time,Internship,Contract',
									  'salary'         => 'required|numeric',
									  'location'       => 'required|string|max:255',
									  'description'    => 'required|string',
									  'requirements'   => 'required|string',
									  'benefits'       => 'sometimes|string',
//									  'logo'           => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
								 
								 ];
						  } else {
								 if (!$admin->hasPermissionTo('manage-own-company')) {
										return responseJson(403, 'Unauthorized');
								 }
								 
								 // Check if admin already has a company
								 if (!$admin->company_id) {
										return responseJson(
											 403,
											 'You not have a company and cannot create job'
										);
								 }
								 
								 $validationRules = [
									  'name'         => 'required|string|max:255|unique:companies',
									  'logo'         => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
									  'description'  => 'required|string|max:1000',
									  'location'     => 'required|string|max:255',
									  'website'      => 'sometimes|url',
									  'size'         => 'required|string|max:255',
									  // Make size required
									  'hired_people' => 'required|numeric|min:5',
								 ];
						  }
						  
						  // Add custom validation messages
						  $validationCustomMessages = [
								'name.unique'      => 'A company with this name already exists.',
								'name.max'         => 'Company name cannot exceed 255 characters.',
								'logo.image'       => 'The logo must be an image.',
								'logo.mimes'       => 'The logo must be a file of type: jpeg, png, jpg, gif, svg.',
								'logo.max'         => 'The logo cannot exceed 2MB in size.',
								'description.max'  => 'Company description cannot exceed 1000 characters.',
								'location.max'     => 'Location cannot exceed 255 characters.',
								'size.max'         => 'Company size cannot exceed 255 characters.',
								'hired_people.min' => 'Hired people count cannot be negative.',
						  ];
						  
						  // Validate request data
						  $validated = $request->validate(
								$validationRules, $validationCustomMessages
						  );
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
						  return responseJson(
								422,
								" validation error",
								$e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  // Handle other exceptions
						  Log::error('Server Error: ' . $e->getMessage());
						  // For production: Generic error message
						  $errorMessage
								= "Server error: Something went wrong. Please try again later.";
						  // For development: Detailed error message
						  if (config('app.debug')) {
								 $errorMessage = "Server error: " . $e->getMessage();
						  }
						  return responseJson(500, $errorMessage);
					}
					
					
					/** @var Admin $admin */
					$admin = auth('admin')->user();
					
					// Authorization check
					if (!$admin || !$admin->hasPermissionTo('manage-company-jobs')) {
						  return responseJson(403, 'Unauthorized');
					}
					
					// Validate input
					$validated = $request->validate([
						 'title'       => 'required|string|max:255',
						 'description' => 'required|string',
						 'categories'  => 'required|array|exists:categories,id'
					]);
					
					// Company check
					if (!$admin->company_id) {
						  return responseJson(
								403, 'No company associated with this account'
						  );
					}
					
					// Create a job
					$job = $admin->company->jobs()->create($validated);
					$job->categories()->sync($request->categories);
					
					return responseJson(201, 'Job created', $job);
			 }
			 
			 public function update(Request $request, Job $job)
			 {
					/** @var Admin $admin */
					$admin = auth('admin')->user();
					
					// Validate input
					$validated = $request->validate([
						 'title'       => 'sometimes|string|max:255',
						 'description' => 'sometimes|string',
						 'status'      => 'sometimes|in:open,close',
						 'category'    => 'sometimes|array|exists:categories,id'
					]);
					
					// Authorization check using policy
					if (!$admin || !$admin->can('update', $job)) {
						  return responseJson(403, 'Unauthorized');
					}
					
					$job->update($validated);
					if ($request->has('category')) {
						  $job->category()->sync($request->category);
					}
					return responseJson(200, 'Job updated', $job);
			 }
			 
			 public function destroy(Job $job)
			 {
					/** @var Admin $admin */
					$admin = auth('admin')->user();
					
					// Authorization check using policy
					if (!$admin || !$admin->can('delete', $job)) {
						  return responseJson(403, 'Unauthorized');
					}
					
					$job->delete();
					
					return responseJson(200, 'Job deleted');
			 }
	  }