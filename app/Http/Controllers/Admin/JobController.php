<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Admin;
	  use App\Models\JobListing as Job;
	  use Illuminate\Http\Request;
	  
	  class JobController extends Controller
	  {
			 public function index(Request $request)
			 {
					$jobs = Job::with('company', 'categories')
						 ->when($request->category, function($query) use ($request) {
								$query->whereHas('categories', fn($q) => $q->where('slug', $request->category));
						 })
						 ->paginate(15);
					
					return responseJson(200, 'Jobs retrieved', $jobs);
			 }
			 
			 public function show(Job $job)
			 {
					return responseJson(200, 'Job details', [
						 'job' => $job->load('company', 'categories'),
						 'similar_jobs' => Job::whereHas('categories', fn($q) => $q->whereIn('id', $job->categories->pluck('id')))
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
						 'categories' => 'required|array|exists:categories,id'
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
						 'categories' => 'sometimes|array|exists:categories,id'
					]);
					
					// Authorization check using policy
					if (!$admin || !$admin->can('update', $job)) {
						  return responseJson(403, 'Unauthorized');
					}
					
					$job->update($validated);
					if ($request->has('categories')) {
						  $job->categories()->sync($request->categories);
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