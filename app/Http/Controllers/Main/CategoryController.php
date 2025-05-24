<?php
	  
	  namespace App\Http\Controllers\Main;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Category;
	  use App\Models\JobListing;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Cache;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Storage;
	  use Illuminate\Support\Str;
	  use Illuminate\Validation\ValidationException;
	  
	  class CategoryController extends Controller
	  {
			 /**
			  * List categories for admin (paginated) or API users (trending/popular).
			  */
			 public function index(Request $request): JsonResponse
			 {
					try {
						  if (auth('admin')->check()) {
								 return $this->handleAdminCategories($request);
						  }
						  
						  if (auth('api')->check()) {
								 return $this->handleApiUserCategories($request);
						  }
						  
						  return responseJson(401, 'Unauthenticated');
					} catch (\Exception $e) {
						  Log::error('Category index error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve categories');
					}
			 }
			 
			 /**
			  * Handle category retrieval for admin users with pagination.
			  */
			 private function handleAdminCategories(Request $request): JsonResponse
			 {
					$admin = auth('admin')->user();
					if (!$admin->hasRole('super-admin') && !$admin->hasPermissionTo('manage-categories')) {
						  return responseJson(403, 'Forbidden', 'You do not have permission to view categories');
					}
					
					$page = $request->get('page', 1);
					$cacheKey = "categories_admin_page_{$page}";
					$categories = Cache::store('redis')->remember($cacheKey, now()->addMinutes(10), fn() =>
					Category::select('id', 'name', 'slug', 'image', 'created_at', 'updated_at')
						 ->withCount(['jobs' => fn($query) => $query->where('job_status', '!=', 'cancelled')])
						 ->paginate(10)
						 ->through(fn($category) => $this->mapCategoryData($category))
					);
					
					if ($categories->isEmpty()) {
						  return responseJson(404, 'No categories found');
					}
					
					return responseJson(200, 'Categories retrieved successfully', [
						 'categories' => $categories,
						 'total_count' => $categories->total(),
					]);
			 }
			 
			 /**
			  * Handle category retrieval for API users with trending and popular categories.
			  */
			 private function handleApiUserCategories(Request $request): JsonResponse
			 {
					$user = auth('api')->user();
					$categoryCount = Cache::store('redis')->remember('category_count', now()->addMinutes(30), fn() =>
					Category::count()
					);
					
					if ($categoryCount === 0) {
						  return responseJson(404, 'No categories found');
					}
					
					$number = max(1, (int)($categoryCount / 2));
					$cacheKeyTrending = "categories_trending_{$user->id}";
					$cacheKeyPopular = "categories_popular_{$user->id}";
					
					$trendingCategories = Cache::store('redis')->remember($cacheKeyTrending, now()->addMinutes(15), fn() =>
					Category::select('id', 'name', 'slug', 'image')
						 ->withCount(['jobs' => fn($query) => $query->where('job_status', '!=', 'cancelled')])
						 ->with(['jobs' => fn($query) =>
						 $query->select('id', 'title', 'company_id', 'location', 'job_type', 'salary', 'job_status', 'position', 'category_name', 'description', 'requirement', 'benefits')
							  ->where('job_status', '!=', 'cancelled')
							  ->with(['company' => fn($query) => $query->select('id', 'name', 'logo')])
							  ->take(5)
						 ])
						 ->inRandomOrder()
						 ->take($number)
						 ->get()
						 ->map(fn($category) => $this->mapCategoryData($category, true))
					);
					
					$popularCategories = Cache::store('redis')->remember($cacheKeyPopular, now()->addMinutes(15), fn() =>
					Category::select('id', 'name', 'slug', 'image')
						 ->withCount(['jobs' => fn($query) => $query->where('job_status', '!=', 'cancelled')])
						 ->with(['jobs' => fn($query) =>
						 $query->select('id', 'title', 'company_id', 'location', 'job_type', 'salary', 'job_status', 'position', 'category_name', 'description', 'requirement', 'benefits')
							  ->where('job_status', '!=', 'cancelled')
							  ->with(['company' => fn($query) => $query->select('id', 'name', 'logo')])
							  ->take(5)
						 ])
						 ->inRandomOrder()
						 ->take($number)
						 ->get()
						 ->map(fn($category) => $this->mapCategoryData($category, true))
					);
					
					return responseJson(200, 'Categories retrieved successfully', [
						 'Trending' => $trendingCategories,
						 'Popular' => $popularCategories,
					]);
			 }
			 
			 /**
			  * Show a specific category with its jobs.
			  */
			 public function show(int $categoryId): JsonResponse
			 {
					try {
						  if (!auth('api')->check() && !auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  if (auth('admin')->check()) {
								 $admin = auth('admin')->user();
								 if (!$admin->hasRole('super-admin') && !$admin->hasPermissionTo('manage-categories')) {
										return responseJson(403, 'Forbidden', 'You do not have permission to view this category');
								 }
						  }
						  
						  $cacheKey = "category_{$categoryId}";
						  $category = Cache::store('redis')->remember($cacheKey, now()->addMinutes(10), fn() =>
						  Category::select('id', 'name', 'slug', 'image', 'created_at', 'updated_at')
								->withCount(['jobs' => fn($query) => $query->where('job_status', '!=', 'cancelled')])
								->findOrFail($categoryId)
						  );
						  
						  $jobs = JobListing::select('id', 'title', 'company_id', 'location', 'job_type', 'job_status', 'salary', 'position', 'category_name', 'description', 'requirement', 'benefits')
								->where('category_name', $category->name)
								->where('job_status', '!=', 'cancelled')
								->with(['company' => fn($query) => $query->select('id', 'name', 'logo')])
								->paginate(10)
								->through(fn($job) => $this->mapJobData($job));
						  
						  $companiesCount = JobListing::where('category_name', $category->name)
								->where('job_status', '!=', 'cancelled')
								->distinct('company_id')
								->count('company_id');
						  
						  return responseJson(200, 'Category details retrieved', [
								'category' => array_merge($this->mapCategoryData($category), [
									 'companies_count' => $companiesCount,
									 'jobs' => $jobs,
								]),
						  ]);
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Category not found');
					} catch (\Exception $e) {
						  Log::error('Category show error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve category');
					}
			 }
			 
			 /**
			  * Create a new category.
			  */
			 public function store(Request $request): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  if (!$admin->hasRole('super-admin')) {
								 return responseJson(403, 'Forbidden', 'You do not have permission to create categories');
						  }
						  
						  $validated = $request->validate($this->getValidationRules(), $this->getValidationMessages());
						  
						  $validated['slug'] = Str::slug($validated['name']);
						  $validated['image'] = $this->handleImageUpload($request, $validated['slug']);
						  
						  $category = DB::transaction(fn() => Category::create($validated));
						  
						  // Invalidate caches
						  $this->invalidateCategoryCaches($category->id);
						  
						  return responseJson(201, 'Category created successfully', [
								'category' => $this->mapCategoryData($category),
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (\Exception $e) {
						  Log::error('Category store error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to create category');
					}
			 }
			 
			 /**
			  * Update an existing category.
			  */
			 public function update(Request $request, int $categoryId): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  if (!$admin->hasRole('super-admin')) {
								 return responseJson(403, 'Forbidden', 'You do not have permission to update categories');
						  }
						  
						  $category = Category::findOrFail($categoryId);
						  
						  $validationRules = $this->getValidationRules(true, $categoryId);
						  $validated = $request->validate($validationRules, $this->getValidationMessages());
						  
						  $originalData = $category->only(['name', 'slug', 'image']);
						  $changes = array_filter($validated, fn($value, $key) => $originalData[$key] !== $value, ARRAY_FILTER_USE_BOTH);
						  
						  if (empty($changes)) {
								 return responseJson(200, 'No changes detected', [
									  'category' => $this->mapCategoryData($category),
									  'unchanged' => true,
								 ]);
						  }
						  
						  if (isset($validated['name']) && $validated['name'] !== $category->name) {
								 $validated['slug'] = Str::slug($validated['name']);
								 // Update jobs with new category name
								 JobListing::where('category_name', $category->name)
									  ->update(['category_name' => $validated['name']]);
						  }
						  
						  if ($request->hasFile('image')) {
								 $validated['image'] = $this->handleImageUpload($request, $validated['slug'] ?? $category->slug, $category->image);
						  }
						  
						  DB::transaction(fn() => $category->update($validated));
						  
						  // Invalidate caches
						  $this->invalidateCategoryCaches($categoryId);
						  
						  return responseJson(200, 'Category updated successfully', [
								'category' => $this->mapCategoryData($category->fresh()),
								'changes' => $changes,
								'unchanged' => false,
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Category not found');
					} catch (\Exception $e) {
						  Log::error('Category update error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to update category');
					}
			 }
			 
			 /**
			  * Delete a category.
			  */
			 public function destroy(int $categoryId): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  if (!$admin->hasRole('super-admin')) {
								 return responseJson(403, 'Forbidden', 'You do not have permission to delete categories');
						  }
						  
						  $category = Category::findOrFail($categoryId);
						  
						  DB::transaction(function () use ($category) {
								 if ($category->image && !str_contains($category->image, 'still_images')) {
										Storage::disk('public')->delete(str_replace(Storage::disk('public')->url(''), '', $category->image));
								 }
								 // Update jobs to a default category or null
								 JobListing::where('category_name', $category->name)
									  ->update(['category_name' => null]);
								 $category->delete();
						  });
						  
						  // Invalidate caches
						  $this->invalidateCategoryCaches($categoryId);
						  
						  return responseJson(200, 'Category deleted successfully');
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Category not found');
					} catch (\Exception $e) {
						  Log::error('Category destroy error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to delete category');
					}
			 }
			 
			 /**
			  * Map category data for consistent response format.
			  */
			 private function mapCategoryData(Category $category, bool $includeJobs = false): array
			 {
					$data = [
						 'id' => $category->id,
						 'name' => $category->name,
						 'description' => $category->slug,
						 'image' => $category->image,
						 'jobs_count' => $category->jobs_count ?? 0,
						 'companies_count' => JobListing::where('category_name', $category->name)
							  ->where('job_status', '!=', 'cancelled')
							  ->distinct('company_id')
							  ->count('company_id'),
						 'created_at' => $category->created_at?->toDateTimeString(),
						 'updated_at' => $category->updated_at?->toDateTimeString(),
					];
					
					if ($includeJobs && $category->relationLoaded('jobs')) {
						  $data['jobs'] = $category->jobs->map(fn($job) => $this->mapJobData($job));
					}
					
					return $data;
			 }
			 
			 /**
			  * Map job data for consistent response format.
			  */
			 private function mapJobData(JobListing $job): array
			 {
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
			 }
			 
			 /**
			  * Get validation rules for category creation or update.
			  */
			 private function getValidationRules(bool $isUpdate = false, ?int $categoryId = null): array
			 {
					$rules = [
						 'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
						 'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
					];
					
					if ($isUpdate) {
						  $rules['name'] = ['sometimes', 'string', 'max:255', "unique:categories,name,{$categoryId}"];
						  $rules['image'] = ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'];
					}
					
					return $rules;
			 }
			 
			 /**
			  * Get validation messages for category operations.
			  */
			 private function getValidationMessages(): array
			 {
					return [
						 'name.required' => 'The category name is required.',
						 'name.max' => 'Category name cannot exceed 255 characters.',
						 'name.unique' => 'The category name is already taken.',
						 'image.required' => 'An image is required.',
						 'image.image' => 'The file must be an image.',
						 'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif, svg.',
						 'image.max' => 'The image may not be larger than 2048 kilobytes.',
					];
			 }
			 
			 /**
			  * Handle image upload and cleanup for categories.
			  */
			 private function handleImageUpload(Request $request, string $slug, ?string $existingImage = null): string
			 {
					if ($request->hasFile('image')) {
						  if ($existingImage && !str_contains($existingImage, 'still_images')) {
								 Storage::disk('public')->delete(str_replace(Storage::disk('public')->url(''), '', $existingImage));
						  }
						  $image = $request->file('image');
						  $imageName = time() . '_' . $slug . '.' . $image->getClientOriginalExtension();
						  $imagePath = $image->storeAs('category_images', $imageName, 'public');
						  return Storage::disk('public')->url($imagePath);
					}
					return $existingImage ?? 'https://jobizaa.com/still_images/category.png';
			 }
			 
			 /**
			  * Invalidate category, job, and company-related caches.
			  */
			 private function invalidateCategoryCaches(?int $categoryId): void
			 {
					Cache::store('redis')->forget('category_count');
					if ($categoryId) {
						  Cache::store('redis')->forget("category_{$categoryId}");
					}
					
					// Invalidate admin category pages
					$page = 1;
					while (Cache::store('redis')->has("categories_admin_page_{$page}")) {
						  Cache::store('redis')->forget("categories_admin_page_{$page}");
						  $page++;
					}
					
					// Invalidate user-specific trending and popular categories
					$userIds = auth('api')->check() ? [auth('api')->user()->id] : \App\Models\User::pluck('id');
					foreach ($userIds as $userId) {
						  Cache::store('redis')->forget("categories_trending_{$userId}");
						  Cache::store('redis')->forget("categories_popular_{$userId}");
						  Cache::store('redis')->forget("recommended_jobs_{$userId}");
					}
					
					// Invalidate job and company caches
					Cache::store('redis')->forget('open_jobs_count');
					Cache::store('redis')->forget('trending_jobs');
					Cache::store('redis')->forget('popular_jobs');
					Cache::store('redis')->forget('trending_companies');
					Cache::store('redis')->forget('popular_companies');
					
					// Invalidate job and company job pages
					$companyIds = \App\Models\Company::pluck('id');
					foreach ($companyIds as $companyId) {
						  Cache::store('redis')->forget("company_{$companyId}_details");
						  $page = 1;
						  while (Cache::store('redis')->has("company_{$companyId}_jobs_page_{$page}")) {
								 Cache::store('redis')->forget("company_{$companyId}_jobs_page_{$page}");
								 $page++;
						  }
					}
					
					$page = 1;
					while (Cache::store('redis')->has("admin_jobs_page_{$page}")) {
						  Cache::store('redis')->forget("admin_jobs_page_{$page}");
						  $page++;
					}
			 }
	  }