<?php
	  
	  namespace App\Http\Controllers\Main;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Category;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Str;
	  
	  class CategoryController extends Controller
	  {
			 public function index(Request $request): \Illuminate\Http\JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $categories = Category::all();
						  
						  return responseJson(
								200, 'Categories retrieved successfully', [
								'categories' => $categories,
						  ]
						  );
						  
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 public function show(Category $category
			 ): \Illuminate\Http\JsonResponse {
					try {
						  // Check authentication
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  // Check if the category exists
						  if (!$category) {
								 return responseJson(404, 'Category not found');
						  }
						  
						  return responseJson(200, 'Category details retrieved', [
								'category' => $category,
						  ]);
						  
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, $errorMessage);
					}
			 }
			 
			 public function store(Request $request): \Illuminate\Http\JsonResponse
			 {
					try {
						  // Check authentication
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  
						  // Check authorization
						  if (!$admin->hasPermissionTo('manage-categories')) {
								 return responseJson(403, 'Unauthorized');
						  }
						  
						  // Validate request data
						  $validated = $request->validate([
								'name' => 'required|string|max:255|unique:categories,name',
						  ]);
						  
						  // Generate slug
						  $validated['slug'] = Str::slug($validated['name'], '-');
						  
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
			 
			 public function update(Request $request, Category $category
			 ): \Illuminate\Http\JsonResponse {
					try {
						  // Check authentication
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  
						  // Check authorization
						  if (!$admin->hasPermissionTo('manage-categories')) {
								 return responseJson(403, 'Unauthorized');
						  }
						  
						  // Check if the category exists
						  if (!$category) {
								 return responseJson(404, 'Category not found');
						  }
						  
						  // Validate request data
						  $validated = $request->validate([
								'name' => 'sometimes|string|max:255|unique:categories,name,'
									 . $category->id,
						  ]);
						  
						  // Update slug if name changes
						  if (isset($validated['name'])
								&& $validated['name'] !== $category->name
						  ) {
								 $validated['slug'] = Str::slug(
									  $validated['name'], '-'
								 );
						  }
						  
						  // Update category
						  $category->update($validated);
						  
						  return responseJson(200, 'Category updated successfully', [
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
			 
			 public function destroy(Category $category
			 ): \Illuminate\Http\JsonResponse {
					try {
						  // Check authentication
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $admin = auth('admin')->user();
						  
						  // Check authorization
						  if (!$admin->hasPermissionTo('manage-categories')) {
								 return responseJson(403, 'Unauthorized');
						  }
						  
						  // Check if the category exists
						  if (!$category) {
								 return responseJson(404, 'Category not found');
						  }
						  
						  // Delete category
						  $category->delete();
						  
						  return responseJson(200, 'Category deleted successfully');
						  
					} catch (\Exception $e) {
						  Log::error('Server Error: ' . $e->getMessage());
						  $errorMessage = config('app.debug') ? $e->getMessage()
								: 'Server error: Something went wrong. Please try again later.';
						  return responseJson(500, $errorMessage);
					}
			 }
	  }
