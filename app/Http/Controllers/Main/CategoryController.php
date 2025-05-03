<?php
	  
	  namespace App\Http\Controllers\Main;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Category;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Storage;
	  use Illuminate\Support\Str;
	  
	  class CategoryController extends Controller
	  {
			 public function index(Request $request): JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth()->check() && !auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  // Retrieve categories with their associated jobs, ordered by priority
						  $categories = Category::with('jobs')->paginate(10);
						  
						  if ($categories->isEmpty()) {
								 return responseJson(404, 'No categories found');
						  }
						  $responseData = [];
						  foreach ($categories as $category) {
								 $responseData[] = [
									  'name' => $category->name,
									  'image' => $category->image,
									  'jobs' => $category->jobs
								 ];
						  }
						  return responseJson(
								200, 'Categories retrieved successfully',$responseData
						  );
						  
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 public function show($categoryId): JsonResponse
			 {
					try {
						  // Check authentication for both admin and regular users
						  if (!auth()->check() && !auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  // Find the category
						  $category = Category::with('jobs')->find($categoryId);
						  
						  // Check if the category exists
						  if (!$category) {
								 return responseJson(404, 'Category not found');
						  }
						  
						  return responseJson(
								200, 'Category details retrieved', $category
						  );
						  
					} catch (\Exception $e) {
						  // Handle exceptions
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 public function store(Request $request): JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  
						  // Check authorization
						  if (!$admin->hasRole('super-admin')) {
								 return responseJson(403, 'Unauthorized');
						  }
						  
						  // Validate request data including image
						  $validated = $request->validate([
								'name'  => 'required|string|max:255|unique:categories,name',
								'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
								// Updated validation
						  ]);
						  
						  // Generate slug
						  $validated['slug'] = Str::slug($validated['name'], '-');
						  
						  // Store the image
						  if ($request->hasFile('image')) {
								 $image = $request->file('image');
								 $imageName = time() . '_' . Str::slug(
											$validated['name']
									  ) . '.' . $image->getClientOriginalExtension();
								 $imagePath = $image->storeAs(
									  'category_images', $imageName, 'public'
								 );
								 $urlPath =Storage::disk('public')->url($imagePath);
								 
								 $validated['image'] = $urlPath;
						  } else {
								 // Set default image URL
								 $validated['logo']
									  = 'https://jobizaa.com/still_images/category.png';
						  }
						  
						  // Create category
						  $category = Category::create($validated);
						  
						  return responseJson(201, 'Category created successfully', [
								'category' => $category,
						  ]);
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 public function update(Request $request, $categoryId): JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  
						  // Check authorization
						  if (!$admin->hasRole('super-admin')) {
								 return responseJson(403, 'Unauthorized');
						  }
						  
						  // Find the category
						  $category = Category::find($categoryId);
						  
						  // Check if the category exists
						  if (!$category) {
								 return responseJson(404, 'Category not found');
						  }
						  
						  // Validate request data including image
						  $validated = $request->validate([
								'name'  => 'sometimes|string|max:255',
								'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
								// Updated validation
						  ]);
						  
						  // Get original data before update
						  $originalData = $category->only(['name', 'slug', 'image']
						  ); // Include image in original data
						  
						  // Check if any data actually changed
						  $changes = [];
						  foreach ($validated as $key => $value) {
								 if ($originalData[$key] !== $value) {
										$changes[$key] = [
											 'from' => $originalData[$key],
											 'to'   => $value
										];
								 }
						  }
						  
						  if (empty($changes)) {
								 return responseJson(200, 'No changes detected', [
									  'category'  => $category,
									  'unchanged' => true
								 ]);
						  }
						  
						  // Update slug if name changes
						  if (isset($validated['name'])
								&& $validated['name'] !== $category->name
						  ) {
								 $validated['slug'] = Str::slug(
									  $validated['name'], '-'
								 );
						  }
						  
						  // Handle image upload and deletion
						  if ($request->hasFile('image')) {
								 // Delete the old image if it exists
								 if ($category->image) {
										Storage::disk('public')->delete($category->image);
								 }
								 
								 $image = $request->file('image');
								 $imageName = time() . '_' . Str::slug(
											$validated['name'] ?? $category->name
									  ) . '.' . $image->getClientOriginalExtension();
								 $imagePath = $image->storeAs(
									  'category_images', $imageName, 'public'
								 );
								 $urlPath =Storage::disk('public')->url($imagePath);
								 $validated['image'] = $urlPath;
						  } elseif (isset($validated['image'])) {
								 // If the image field is present but no file is uploaded, remove the image entry
								 $validated['image'] = null;
						  }
						  
						  // Update category
						  $category->update($validated);
						  
						  return responseJson(200, 'Category updated successfully', [
								'category'  => $category,
								'changes'   => $changes,
								'unchanged' => false
						  ]);
						  
					} catch (\Illuminate\Validation\ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 public function destroy($categoryId): JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth('admin')->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  
						  // Check authorization
						  if (!$admin->hasRole('super-admin')) {
								 return responseJson(403, 'Unauthorized');
						  }
						  
						  // Find the category
						  $category = Category::find($categoryId);
						  
						  // Check if the category exists
						  if (!$category) {
								 return responseJson(404, 'Category not found');
						  }
						  
						  // Delete the associated image if it exists
						  if ($category->image) {
								 Storage::disk('public')->delete($category->image);
						  }
						  
						  // Delete category
						  $category->delete();
						  
						  return responseJson(200, 'Category deleted successfully');
						  
					} catch (\Exception $e) {
						  // Handle exceptions
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, $errorMessage);
					}
			 }
	  }
