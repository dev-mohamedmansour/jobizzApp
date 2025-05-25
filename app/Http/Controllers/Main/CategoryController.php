<?php
	  
	  namespace App\Http\Controllers\Main;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Category;
	  use App\Models\JobListing;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Storage;
	  use Illuminate\Support\Str;
	  use Illuminate\Validation\ValidationException;
	  
	  class CategoryController extends Controller
	  {
			 /**
			  * List categories for admin (paginated) or API users (trending/popular).
			  *
			  * @param Request $request
			  * @return JsonResponse
			  */
			 public function index(Request $request): JsonResponse
			 {
					try {
						  if (auth('admin')->check()) {
								 $categories =
								 Category::withCount('jobs')->get();
								 if ($categories->isEmpty()) {
										return responseJson(404, 'Not found', 'No categories found');
								 }
								 // Transform categories into a clean array
								 $responseData = $categories->map(function ($category) {
										$companiesCount = JobListing::where('category_name', $category->name)
											 ->distinct('company_id')
											 ->count('company_id');
										return [
											 'id' => $category->id,
											 'name' => $category->name,
											 'description' => $category->slug,
											 'image' => $category->image,
											 'created_at' => $category->created_at,
											 'updated_at' => $category->updated_at,
											 'jobs_count' => $category->jobs_count,
											 'companies_count' => $companiesCount,
										];
								 })->all();
								 return responseJson(200, 'Categories retrieved successfully', $responseData);
						  }
						  
						  $user = auth('api')->user();
						  $profile = $user->defaultProfile->first();
						  $categoryNum = Category::count();
						  
						  if ($categoryNum === 0) {
								 return responseJson(404, 'Not found', 'No categories found');
						  }
						  
						  $number = max(1, (int) ($categoryNum / 2));
						  
						  $categoryTrending = Category::with(['jobs' => fn($query)  => $query->select([
								'id', 'title', 'company_id', 'location', 'job_type', 'salary', 'job_status','position', 'category_name', 'description', 'requirement', 'benefits'
						  ])->with(['company' => fn($query) => $query->select(['id', 'name', 'logo'])])])
								->inRandomOrder()
								->withCount('jobs')
								->take($number)
								->get()
								->map(function ($category) use ($profile) {
									  $companiesCount = JobListing::where('category_name', $category->name)
											->distinct('company_id')
											->count('company_id');
									  return [
											'id' => $category->id,
											'name' => $category->name,
											'description' => $category->slug,
											'image' => $category->image,
											'companies_count' => $companiesCount,
											'jobs_count' => $category->jobs_count,
											'jobs' => $category->jobs->map(function ($job) use($profile) {
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
														'isFavorite'   => $job->isFavoritedByProfile($profile->id),
														'companyName' => $job->company->name ?? null,
														'companyLogo' => $job->company->logo ?? null,
												  ];
											})
									  ];
								}
						  );
						  
						  $categoryPopular = Category::with(['jobs' => fn($query) => $query->select([
								'id', 'title', 'company_id', 'location', 'job_type', 'salary', 'position', 'job_status','category_name', 'description', 'requirement', 'benefits'
						  ])->with(['company' => fn($query) => $query->select(['id', 'name', 'logo'])])])
								->inRandomOrder()
								->withCount('jobs')
								->take($number)
								->get()
								->map(function ($category) use ($profile){
									  $companiesCount = JobListing::where('category_name', $category->name)
											->distinct('company_id')
											->count('company_id');
									  return [
											'id' => $category->id,
											'name' => $category->name,
											'description' => $category->slug,
											'image' => $category->image,
											'companies_count' => $companiesCount,
											'jobs_count' => $category->jobs_count,
											'jobs' => $category->jobs->map(function ($job) use ($profile) {
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
														'isFavorite'   => $job->isFavoritedByProfile($profile->id),
														'companyName' => $job->company->name ?? null,
														'companyLogo' => $job->company->logo ?? null,
												  ];
											})
									  ];
								}
						  );
						  
						  return responseJson(200, 'Categories retrieved successfully', [
								'Trending' => $categoryTrending,
								'Popular' => $categoryPopular,
						  ]);
					} catch (\Exception $e) {
						  Log::error('Category index error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Something went wrong');
					}
			 }
			 
			 /**
			  * Show a specific category with its jobs.
			  *
			  * @param int $categoryId
			  *
			  * @return JsonResponse
			  */
			 
			 
			 public function show(int $categoryId): JsonResponse
			 {
					try {
						  if (auth('admin')->check()) {
								 return $this->handleAdminJobs($categoryId);
						  }
						  
						  if (auth('api')->check()) {
								 return $this->handleApiUserJobs($categoryId);
						  }
						  
						  return responseJson(
								403, 'Forbidden', 'Invalid authentication guard'
						  );
					} catch (\Exception $e) {
						  Log::error('Index error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Create a new category.
			  *
			  * @param Request $request
			  * @return JsonResponse
			  */
			 public function store(Request $request): JsonResponse
			 {
					try {
						  if (!auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated', 'Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  if (!$admin->hasRole('super-admin')) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $validated = $request->validate([
								'name' => 'required|string|max:255|unique:categories,name',
								'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
						  ]);
						  
						  $validated['slug'] = Str::slug($validated['name']);
						  
						  if ($request->hasFile('image')) {
								 $image = $request->file('image');
								 $imageName = time() . '_' . $validated['slug'] . '.' . $image->getClientOriginalExtension();
								 $imagePath = $image->storeAs('category_images', $imageName, 'public');
								 $validated['image'] = Storage::disk('public')->url($imagePath);
						  } else {
								 $validated['image'] = 'https://jobizaa.com/still_images/category.png';
						  }
						  $category = Category::create($validated);
						  
						  return responseJson(201, 'Category created successfully', ['category' => $category]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (\Exception $e) {
						  Log::error('Category store error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Something went wrong');
					}
			 }
			 
			 /**
			  * Update an existing category.
			  *
			  * @param Request $request
			  * @param int     $categoryId
			  *
			  * @return JsonResponse
			  */
			 public function update(Request $request, int $categoryId): JsonResponse
			 {
					try {
						  if (!auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated', 'Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  if (!$admin->hasRole('super-admin')) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $category = Category::find($categoryId);
						  if (!$category) {
								 return responseJson(404, 'Not found', 'Category not found');
						  }
						  
						  $validated = $request->validate([
								'name' => 'sometimes|string|max:255|unique:categories,name,' . $categoryId,
								'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
						  ]);
						  
						  $originalData = $category->only(['name', 'slug', 'image']);
						  $changes = array_filter($validated, fn($value, $key) => $originalData[$key] !== $value, ARRAY_FILTER_USE_BOTH);
						  
						  if (empty($changes)) {
								 return responseJson(200, 'No changes detected', ['category' => $category, 'unchanged' => true]);
						  }
						  
						  if (isset($validated['name']) && $validated['name'] !== $category->name) {
								 $validated['slug'] = Str::slug($validated['name']);
						  }
						  
						  if ($request->hasFile('image')) {
								 if ($category->image && !str_contains($category->image, 'still_images')) {
										Storage::disk('public')->delete(str_replace(Storage::disk('public')->url(''), '', $category->image));
								 }
								 $image = $request->file('image');
								 $imageName = time() . '_' . ($validated['slug'] ?? $category->slug) . '.' . $image->getClientOriginalExtension();
								 $imagePath = $image->storeAs('category_images', $imageName, 'public');
								 $validated['image'] = Storage::disk('public')->url($imagePath);
						  }
						  
						  $category->update($validated);
						  
						  return responseJson(200, 'Category updated successfully', [
								'category' => $category,
								'changes' => $changes,
								'unchanged' => false,
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (\Exception $e) {
						  Log::error('Category update error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Something went wrong');
					}
			 }
			 
			 /**
			  * Delete a category.
			  *
			  * @param int $categoryId
			  *
			  * @return JsonResponse
			  */
			 public function destroy(int $categoryId): JsonResponse
			 {
					try {
						  if (!auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated', 'Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  if (!$admin->hasRole('super-admin')) {
								 return responseJson(403, 'Forbidden', 'Unauthorized');
						  }
						  
						  $category = Category::find($categoryId);
						  if (!$category) {
								 return responseJson(404, 'Not found', 'Category not found');
						  }
						  
						  if ($category->image && !str_contains($category->image, 'still_images')) {
								 Storage::disk('public')->delete(str_replace(Storage::disk('public')->url(''), '', $category->image));
						  }
						  
						  $category->delete();
						  
						  return responseJson(200, 'Category deleted successfully');
					} catch (\Exception $e) {
						  Log::error('Category destroy error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Something went wrong');
					}
			 }
			 
			 private function handleAdminJobs($categoryId)
			 {
					try {
					$admin = auth('admin')->user();
					
					if (!$admin->hasRole('super-admin')) {
						  return responseJson(
								403, 'Forbidden',
								'You do not have permission to view these categories'
						  );
					}
						
						  $category = Category::select(['id', 'name', 'slug', 'image', 'created_at', 'updated_at'])->withCount('jobs')
								->find($categoryId);
						  
						  if (!$category) {
								 return responseJson(404, 'Not found', 'Category not found');
						  }
						  
						  // Fetch jobs where category_name matches the category's name
						  $jobs = JobListing::select([
								'id', 'title', 'company_id', 'location', 'job_type','job_status','salary', 'position',
								'category_name', 'description', 'requirement', 'benefits'
						  ])
								->where('category_name', $category->name)
								->with(['company' => fn($query) => $query->select(['id', 'name', 'logo'])])
								->get();
						  // Count distinct companies for this category
						  $companiesCount = JobListing::where('category_name', $category->name)
								->distinct('company_id')
								->count('company_id');
						  // Transform the response to match the desired structure
						  $responseData = [
								'id' => $category->id,
								'name' => $category->name,
								'description' => $category->slug, // Map slug to description as per snippet
								'image' => $category->image,
								'companies_count' => $companiesCount,
								'jobs_count' => $category->jobs_count,
								'jobs' => $jobs->map(function ($job) {
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
											'companyName' => $job->company->name ?? null,
											'companyLogo' => $job->company->logo ?? null,
									  ];
								})
						  ];
						  
						  return responseJson(200, 'Category details retrieved', $responseData);
					} catch (\Exception $e) {
						  Log::error('Category show error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Something went wrong');
					}
			 }
			 
			 private function handleApiUserJobs($categoryId): JsonResponse
			 {
					try {
						  $user=auth('api')->user();
								 $profile = $user->defaultProfile->first();
								 
						  $category = Category::select(['id', 'name', 'slug', 'image', 'created_at', 'updated_at'])->withCount('jobs')
								->find($categoryId);
						  
						  if (!$category) {
								 return responseJson(404, 'Not found', 'Category not found');
						  }
						  
						  // Fetch jobs where category_name matches the category's name
						  $jobs = JobListing::select([
								'id', 'title', 'company_id', 'location', 'job_type','job_status','salary', 'position',
								'category_name', 'description', 'requirement', 'benefits'
						  ])
								->where('category_name', $category->name)
								->with(['company' => fn($query) => $query->select(['id', 'name', 'logo'])])
								->get();
						  // Count distinct companies for this category
						  $companiesCount = JobListing::where('category_name', $category->name)
								->distinct('company_id')
								->count('company_id');
						  // Transform the response to match the desired structure
						  $responseData = [
								'id' => $category->id,
								'name' => $category->name,
								'description' => $category->slug, // Map slug to description as per snippet
								'image' => $category->image,
								'companies_count' => $companiesCount,
								'jobs_count' => $category->jobs_count,
								'jobs' => $jobs->map(function ($job) use ($profile){
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
											'isFavorite'   => $job->isFavoritedByProfile($profile->id),
											'companyName' => $job->company->name ?? null,
											'companyLogo' => $job->company->logo ?? null,
									  ];
								})
						  ];
						  
						  return responseJson(200, 'Category details retrieved', $responseData);
					} catch (\Exception $e) {
						  Log::error('Category show error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Something went wrong');
					}
			 }
	  }