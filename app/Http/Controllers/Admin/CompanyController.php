<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Admin;
	  use App\Models\Company;
	  use App\Models\Profile;
	  use App\Models\User;
	  use App\Notifications\JobizzUserNotification;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Cache;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Storage;
	  use Illuminate\Validation\ValidationException;
	  
	  class CompanyController extends Controller
	  {
			 /**
			  * Retrieve a list of companies based on the authenticated user's role and guard.
			  */
			 public function index(): JsonResponse
			 {
					try {
						  if (auth('admin')->check()) {
								 return $this->handleAdminCompanies();
						  }
						  
						  if (auth('api')->check()) {
								 return $this->handleApiUserCompanies(auth('api')->user());
						  }
						  
						  return responseJson(403, 'Forbidden', 'Invalid authentication guard');
					} catch (\Exception $e) {
						  Log::error('Index companies error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve companies');
					}
			 }
			 
			 /**
			  * Handle company retrieval for admin users with pagination.
			  */
			 private function handleAdminCompanies(): JsonResponse
			 {
					$admin = auth('admin')->user();
					if (!$this->isAdminAuthorized($admin)) {
						  return responseJson(403, 'Forbidden', 'You do not have permission to view companies');
					}
					
					try {
						  $page = request()->get('page', 1);
						  $cacheKey = "admin_companies_page_{$page}";
						  $companies = Cache::store('redis')->remember($cacheKey, now()->addMinutes(15), fn() =>
						  Company::query()
								->when(!$admin->hasRole('super-admin'), fn($query) =>
								$query->where('id', $admin->company_id))
								->withCount(['jobs' => fn($query) => $query->where('job_status', '!=', 'cancelled')])
								->paginate(10)
								->through(fn($company) => [
									 'id' => $company->id,
									 'name' => $company->name,
									 'logo' => $company->logo,
									 'description' => $company->description,
									 'location' => $company->location,
									 'website' => $company->website,
									 'size' => $company->size,
									 'hired_people' => $company->hired_people,
									 'created_at' => $company->created_at->toDateTimeString(),
									 'updated_at' => $company->updated_at->toDateTimeString(),
									 'jobs_count' => $company->jobs_count,
								])
						  );
						  
						  if ($companies->isEmpty()) {
								 return responseJson(404, 'No companies found');
						  }
						  
						  return responseJson(200, 'Companies retrieved successfully', $companies);
					} catch (\Exception $e) {
						  Log::error('Handle admin companies error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve companies');
					}
			 }
			 
			 /**
			  * Check if an admin is authorized to view companies.
			  */
			 private function isAdminAuthorized(Admin $admin): bool
			 {
					return $admin->hasRole('super-admin') || $admin->hasPermissionTo('manage-own-company');
			 }
			 
			 /**
			  * Handle company retrieval for API users with trending and popular categories.
			  */
			 private function handleApiUserCompanies(User $user): JsonResponse
			 {
					try {
						  $profile = $user->defaultProfile()->first();
						  $totalCompanies = Cache::store('redis')->remember('total_companies_count', now()->addMinutes(5), fn() =>
						  Company::count()
						  );
						  
						  if ($totalCompanies === 0) {
								 return responseJson(404, 'No companies found');
						  }
						  
						  $companiesPerCategory = max(1, (int)($totalCompanies / 2));
						  $cacheKeyTrending = 'trending_companies';
						  $cacheKeyPopular = 'popular_companies';
						  
						  $trendingCompanies = Cache::store('redis')->remember($cacheKeyTrending, now()->addMinutes(5), fn() =>
						  Company::with(['jobs' => fn($query) => $query->where('job_status', '!=', 'cancelled')
								->select('id', 'company_id', 'title', 'job_status', 'salary')])
								->withCount(['jobs' => fn($query) => $query->where('job_status', '!=', 'cancelled')])
								->inRandomOrder()
								->take($companiesPerCategory)
								->get()
								->map(fn($company) => $this->mapCompanyData($company, $profile))
						  );
						  
						  $popularCompanies = Cache::store('redis')->remember($cacheKeyPopular, now()->addMinutes(5), fn() =>
						  Company::with(['jobs' => fn($query) => $query->where('job_status', '!=', 'cancelled')
								->select('id', 'company_id', 'title', 'job_status', 'salary')])
								->withCount(['jobs' => fn($query) => $query->where('job_status', '!=', 'cancelled')])
								->inRandomOrder()
								->take($companiesPerCategory)
								->get()
								->map(fn($company) => $this->mapCompanyData($company, $profile))
						  );
						  
						  return responseJson(200, 'Companies retrieved successfully', [
								'Trending' => $trendingCompanies,
								'Popular' => $popularCompanies,
						  ]);
					} catch (\Exception $e) {
						  Log::error('Handle API user companies error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve companies');
					}
			 }
			 
			 /**
			  * Map company data for API user response.
			  */
			 private function mapCompanyData(Company $company, Profile $profile): array
			 {
					return [
						 'id' => $company->id,
						 'name' => $company->name,
						 'logo' => $company->logo,
						 'description' => $company->description,
						 'location' => $company->location,
						 'website' => $company->website,
						 'size' => $company->size,
						 'hired_people' => $company->hired_people,
						 'created_at' => $company->created_at->toDateString(),
						 'jobs_count' => $company->jobs_count,
						 'jobs' => $company->jobs->map(fn($job) => [
							  'id' => $job->id,
							  'title' => $job->title,
							  'job_status' => $job->job_status,
							  'job_salary' => $job->salary,
							  'isFavorite' => $job->isFavoritedByProfile($profile->id),
						 ]),
					];
			 }
			 
			 /**
			  * Retrieve details of a specific company.
			  */
			 public function show(int $companyId): JsonResponse
			 {
					try {
						  $cacheKey = "company_{$companyId}_details";
						  $company = Cache::store('redis')->remember($cacheKey, now()->addMinutes(10), fn() =>
						  Company::with(['jobs' => fn($query) => $query->where('job_status', '!=', 'cancelled')])
								->findOrFail($companyId)
						  );
						  
						  if (auth('admin')->check()) {
								 $admin = auth('admin')->user();
								 if (!$this->isAdminAuthorizedToShow($admin, $company)) {
										return responseJson(403, 'Forbidden', 'You do not have permission to view this company');
								 }
						  } elseif (!auth('api')->check()) {
								 return responseJson(403, 'Forbidden', 'You do not have permission to view this company');
						  }
						  
						  $activeJobsCount = $company->jobs()->where('job_status', '!=', 'cancelled')->count();
						  
						  return responseJson(200, 'Company details retrieved', [
								'company' => [
									 'id' => $company->id,
									 'name' => $company->name,
									 'logo' => $company->logo,
									 'description' => $company->description,
									 'location' => $company->location,
									 'website' => $company->website,
									 'size' => $company->size,
									 'hired_people' => $company->hired_people,
									 'created_at' => $company->created_at->toDateTimeString(),
									 'updated_at' => $company->updated_at->toDateTimeString(),
									 'jobs' => $company->jobs->map(fn($job) => [
										  'id' => $job->id,
										  'title' => $job->title,
										  'job_status' => $job->job_status,
										  'salary' => $job->salary,
									 ]),
								],
								'active_jobs' => $activeJobsCount,
						  ]);
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Company not found');
					} catch (\Exception $e) {
						  Log::error('Show company error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to retrieve company');
					}
			 }
			 
			 /**
			  * Check if an admin is authorized to view a specific company.
			  */
			 private function isAdminAuthorizedToShow(Admin $admin, Company $company): bool
			 {
					return $admin->hasRole('super-admin') ||
						 $admin->id === $company->admin_id ||
						 ($admin->hasAnyRole(['hr', 'coo']) && $admin->company_id === $company->id);
			 }
			 
			 /**
			  * Create a new company.
			  */
			 public function store(Request $request): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $validationRules = $this->getStoreValidationRules($admin);
						  $validated = $request->validate($validationRules['rules'], $validationRules['messages']);
						  $validated = $this->handleLogoUpload($request, $validated);
						  
						  if (!$admin->hasRole('super-admin') && $admin->company_id) {
								 return responseJson(403, 'Forbidden', 'You already have a company and cannot create another');
						  }
						  
						  $company = DB::transaction(function () use ($admin, $validated) {
								 if ($admin->hasRole('super-admin')) {
										$targetAdmin = Admin::findOrFail($validated['admin_id']);
										if ($targetAdmin->company_id) {
											  throw new \Exception('The specified admin already has a company');
										}
										$company = Company::create($validated);
										$targetAdmin->update(['company_id' => $company->id]);
								 } else {
										$validated['admin_id'] = $admin->id;
										$company = Company::create($validated);
										$admin->update(['company_id' => $company->id]);
								 }
								 return $company;
						  });
						  
						  // Queue notifications to users
						  User::all()->each(fn($user) => $user->notify(
								new JobizzUserNotification(
									 title: 'New Company Added',
									 body: "A new company, {$company->name}, has been added to Jobizz.",
									 data: ['company' => $company->name]
								)
						  ));
						  
						  // Invalidate caches
						  $this->invalidateCompanyCaches();
						  
						  return responseJson(201, 'Company created successfully', [
								'company' => $company,
								'hired_people' => $company->hired_people,
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (\Exception $e) {
						  Log::error('Store company error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to create company');
					}
			 }
			 
			 /**
			  * Get validation rules and messages for storing a company.
			  */
			 private function getStoreValidationRules(Admin $admin): array
			 {
					$rules = [
						 'name' => ['required', 'string', 'max:255', 'unique:companies,name'],
						 'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
						 'description' => ['required', 'string', 'max:1000'],
						 'location' => ['required', 'string', 'max:255'],
						 'website' => ['nullable', 'url'],
						 'size' => ['nullable', 'string', 'max:255'],
						 'hired_people' => ['required', 'numeric', 'min:5'],
					];
					
					if ($admin->hasRole('super-admin')) {
						  $rules['admin_id'] = ['required', 'exists:admins,id'];
					}
					
					return [
						 'rules' => $rules,
						 'messages' => [
							  'name.required' => 'The company name is required.',
							  'name.unique' => 'A company with this name already exists.',
							  'name.max' => 'Company name cannot exceed 255 characters.',
							  'logo.image' => 'The logo must be an image.',
							  'logo.mimes' => 'The logo must be a file of type: jpeg, png, jpg, gif, svg.',
							  'logo.max' => 'The logo cannot exceed 2MB in size.',
							  'description.required' => 'The description is required.',
							  'description.max' => 'Description cannot exceed 1000 characters.',
							  'location.required' => 'The location is required.',
							  'location.max' => 'Location cannot exceed 255 characters.',
							  'website.url' => 'The website must be a valid URL.',
							  'size.max' => 'Company size cannot exceed 255 characters.',
							  'hired_people.required' => 'Hired people count is required.',
							  'hired_people.min' => 'Hired people count must be at least 5.',
							  'admin_id.required' => 'Admin ID is required for super-admins.',
							  'admin_id.exists' => 'The specified admin does not exist.',
						 ],
					];
			 }
			 
			 /**
			  * Handle logo upload for company creation or update.
			  */
			 private function handleLogoUpload(Request $request, array $validated, ?Company $company = null): array
			 {
					if ($request->hasFile('logo')) {
						  if ($company && $company->logo && Storage::disk('public')->exists($this->normalizePath($company->logo))) {
								 Storage::disk('public')->delete($this->normalizePath($company->logo));
						  }
						  $path = $request->file('logo')->store('company_logos', 'public');
						  $validated['logo'] = Storage::disk('public')->url($path);
					} elseif (!$company || !$company->logo) {
						  $validated['logo'] = 'https://jobizaa.com/still_images/company.png';
					}
					
					return $validated;
			 }
			 
			 /**
			  * Normalize a file path by removing URL prefix.
			  */
			 private function normalizePath(string $path): string
			 {
					return str_replace(Storage::disk('public')->url(''), '', $path);
			 }
			 
			 /**
			  * Update an existing company.
			  */
			 public function update(Request $request, int $companyId): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $company = Company::findOrFail($companyId);
						  if (!$this->isAdminAuthorizedToShow($admin, $company)) {
								 return responseJson(403, 'Forbidden', 'You do not have permission to update this company');
						  }
						  
						  $validationRules = $this->getUpdateValidationRules($admin, $company);
						  $validated = $request->validate($validationRules['rules'], $validationRules['messages']);
						  $validated = $this->handleLogoUpload($request, $validated, $company);
						  
						  $originalData = $company->only(array_keys($validated));
						  $changes = array_diff_assoc($validated, $originalData);
						  
						  if (empty($changes)) {
								 return responseJson(200, 'No changes detected', [
									  'company' => $company,
									  'hired_people' => $company->hired_people,
									  'unchanged' => true,
								 ]);
						  }
						  
						  DB::transaction(function () use ($admin, $company, $validated) {
								 if ($admin->hasRole('super-admin') && isset($validated['admin_id']) && $validated['admin_id'] !== $company->admin_id) {
										$targetAdmin = Admin::findOrFail($validated['admin_id']);
										if ($targetAdmin->company_id && $targetAdmin->company_id !== $company->id) {
											  throw new \Exception('The specified admin already has a company');
										}
										$company->update($validated);
										$targetAdmin->update(['company_id' => $company->id]);
										Admin::where('company_id', $company->id)->where('id', '!=', $targetAdmin->id)->update(['company_id' => null]);
								 } else {
										unset($validated['admin_id']);
										$company->update($validated);
								 }
						  });
						  
						  // Invalidate caches
						  $this->invalidateCompanyCaches($companyId);
						  
						  return responseJson(200, 'Company updated successfully', [
								'company' => $company->fresh(),
								'hired_people' => $company->hired_people,
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation error', $e->errors());
					} catch (\Exception $e) {
						  Log::error('Update company error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to update company');
					}
			 }
			 
			 /**
			  * Get validation rules and messages for updating a company.
			  */
			 private function getUpdateValidationRules(Admin $admin, Company $company): array
			 {
					$rules = [
						 'name' => ['sometimes', 'string', 'max:255', 'unique:companies,name,' . $company->id],
						 'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
						 'description' => ['sometimes', 'string', 'max:1000'],
						 'location' => ['sometimes', 'string', 'max:255'],
						 'website' => ['sometimes', 'url'],
						 'size' => ['sometimes', 'string', 'max:255'],
						 'hired_people' => ['sometimes', 'numeric', 'min:5'],
					];
					
					if ($admin->hasRole('super-admin')) {
						  $rules['admin_id'] = ['sometimes', 'exists:admins,id'];
					}
					
					return [
						 'rules' => $rules,
						 'messages' => [
							  'name.unique' => 'A company with this name already exists.',
							  'name.max' => 'Company name cannot exceed 255 characters.',
							  'logo.image' => 'The logo must be an image.',
							  'logo.mimes' => 'The logo must be a file of type: jpeg, png, jpg, gif, svg.',
							  'logo.max' => 'The logo cannot exceed 2MB in size.',
							  'description.max' => 'Description cannot exceed 1000 characters.',
							  'location.max' => 'Location cannot exceed 255 characters.',
							  'website.url' => 'The website must be a valid URL.',
							  'size.max' => 'Company size cannot exceed 255 characters.',
							  'hired_people.min' => 'Hired people count must be at least 5.',
							  'admin_id.exists' => 'The specified admin does not exist.',
						 ],
					];
			 }
			 
			 /**
			  * Delete a company and its associated resources.
			  */
			 public function destroy(int $companyId): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $company = Company::findOrFail($companyId);
						  if (!$admin->hasPermissionTo('manage-all-companies') &&
								!($admin->hasPermissionTo('manage-own-company') && $company->id === $admin->company_id)) {
								 return responseJson(403, 'Forbidden', 'You do not have permission to delete this company');
						  }
						  
						  DB::transaction(function () use ($company, $admin) {
								 $company->jobs()->where('job_status', '!=', 'cancelled')->update(['job_status' => 'cancelled']);
								 
								 $subAdmins = Admin::where('company_id', $company->id)->where('id', '!=', $admin->id)->get();
								 foreach ($subAdmins as $subAdmin) {
										if ($subAdmin->photo && Storage::disk('public')->exists($this->normalizePath($subAdmin->photo))) {
											  Storage::disk('public')->delete($this->normalizePath($subAdmin->photo));
										}
								 }
								 
								 Admin::where('company_id', $company->id)->update(['company_id' => null]);
								 if ($company->logo && Storage::disk('public')->exists($this->normalizePath($company->logo))) {
										Storage::disk('public')->delete($this->normalizePath($company->logo));
								 }
								 
								 $company->delete();
						  });
						  
						  // Invalidate caches
						  $this->invalidateCompanyCaches($companyId);
						  
						  return responseJson(200, 'Company and associated resources deleted successfully');
					} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
						  return responseJson(404, 'Company not found');
					} catch (\Exception $e) {
						  Log::error('Destroy company error: ' . $e->getMessage());
						  return responseJson(500, 'Server error', config('app.debug') ? $e->getMessage() : 'Unable to delete company');
					}
			 }
			 
			 /**
			  * Invalidate company-related caches.
			  */
			 private function invalidateCompanyCaches(?int $companyId = null): void
			 {
					Cache::store('redis')->forget('total_companies_count');
					Cache::store('redis')->forget('trending_companies');
					Cache::store('redis')->forget('popular_companies');
					if ($companyId) {
						  Cache::store('redis')->forget("company_{$companyId}_details");
					}
					// Invalidate all admin companies pages
					$page = 1;
					while (Cache::store('redis')->has("admin_companies_page_{$page}")) {
						  Cache::store('redis')->forget("admin_companies_page_{$page}");
						  $page++;
					}
			 }
	  }