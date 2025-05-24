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
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Storage;
	  use Illuminate\Validation\ValidationException;
	  
	  class CompanyController extends Controller
	  {
			 /**
			  * Retrieve a list of companies based on the authenticated user's role and guard.
			  *
			  * @return JsonResponse
			  */
			 public function index(): JsonResponse
			 {
					try {
						  
						  if (auth('admin')->check()) {
								 return $this->handleAdminCompanies();
						  }
						  
						  if (auth('api')->check()) {
								 $user = auth('api')->user();
								 return $this->handleApiUserCompanies($user);
						  }
						  
						  return responseJson(
								403, 'Forbidden', 'Invalid authentication guard'
						  );
					} catch (\Exception $e) {
						  Log::error('Index companies error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Handle company retrieval for admin users.
			  *
			  * @return JsonResponse
			  */
			 private function handleAdminCompanies(): JsonResponse
			 {
					$admin = auth('admin')->user();
					
					if (!$this->isAdminAuthorized($admin)) {
						  return responseJson(
								403, 'Forbidden',
								'You do not have permission to view companies'
						  );
					}
					
					try {
						  $companies = Cache::remember(
								'admin_companies',
								now()->addMinutes(15),
								fn() => Company::withCount(['jobs' => fn($query) => $query->where('job_status', '!=', 'cancelled')])
									 ->get()
									 ->map(function ($company) {
											return [
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
											];
									 })->values()->toArray()
						  );
						  
						  Log::debug('Admin companies data', ['companies' => $companies]);
						  
						  if (empty($companies)) {
								 return responseJson(
									  404, 'No companies found',
									  'No companies found'
								 );
						  }
						  
						  return responseJson(
								200,
								'Companies retrieved successfully',
								$companies
						  );
					} catch (\Exception $e) {
						  Log::error('Handle admin companies error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : 'Unable to retrieve companies'
						  );
					}
			 }
			 /**
			  * Check if an admin is authorized to view all companies.
			  *
			  * @param mixed $admin
			  *
			  * @return bool
			  */
			 private function isAdminAuthorized(mixed $admin): bool
			 {
					return $admin->hasRole('super-admin');
			 }
			 
			 /**
			  * Handle company retrieval for API users.
			  *
			  * @return JsonResponse
			  */
			 private function handleApiUserCompanies($user): JsonResponse
			 {
					$profile = $user->defaultProfile()->first();
					$totalCompanies = Cache::remember(
						 'total_companies_count', now()->addMinutes(5),
						 fn() => Company::count()
					);
					
					if ($totalCompanies === 0) {
						  return responseJson(
								404, 'No companies found', 'No companies found'
						  );
					}
					
					$companiesPerCategory = (int)($totalCompanies / 2);
					
					$trendingCompanies = Cache::remember(
						 'trending_companies', now()->addMinutes(5),
						 fn() => Company::with(['jobs' => fn($query) => $query->where(
							  'job_status', '!=', 'cancelled'
						 )->select('id', 'company_id', 'title', 'job_status','salary')])
							  ->withCount(['jobs' => fn($query) => $query->where('job_status', '!=', 'cancelled')])
							  ->inRandomOrder()
							  ->take($companiesPerCategory)
							  ->get()
							  ->map(function ($company) use ($profile) {
									 return [
										  'id' => $company->id,
										  'name' => $company->name,
										  'logo' => $company->logo,
										  'description'=>$company->description,
										  'location'=>$company->location,
										  'website'=>$company->website,
										  'size'=>$company->size,
										  'hired_people'=>$company->hired_people,
										  'created_at' => $company->created_at->toDateString(),
										  'jobs_count' => $company->jobs_count,
										  'jobs' => $company->jobs->map(function ($job) use ($profile) {
												 return [
													  'id' => $job->id,
													  'title' => $job->title,
													  'job_status' => $job->job_status,
													  'job_salary' => $job->salary,
													  'isFavorite'   => $job->isFavoritedByProfile($profile->id)
												 ];
										  })
									 ];
							  })
					);
					
					$popularCompanies = Cache::remember(
						 'popular_companies', now()->addMinutes(5),
						 fn() => Company::with(['jobs' => fn($query) => $query->where(
							  'job_status', '!=', 'cancelled'
						 )->select('id', 'company_id', 'title', 'job_status','salary')])
							  ->withCount(['jobs' => fn($query) => $query->where('job_status', '!=', 'cancelled')])
							  ->inRandomOrder()
							  ->take($companiesPerCategory)
							  ->get()
							  ->map(function ($company) use ($profile){
									 return [
										  'id' => $company->id,
										  'name' => $company->name,
										  'logo' => $company->logo,
										  'description'=>$company->description,
										  'location'=>$company->location,
										  'website'=>$company->website,
										  'size'=>$company->size,
										  'hired_people'=>$company->hired_people,
										  'created_at' => $company->created_at->toDateString(),
										  'jobs_count' => $company->jobs_count,
										  'jobs' => $company->jobs->map(function ($job) use($profile) {
												 return [
													  'id' => $job->id,
													  'title' => $job->title,
													  'job_status' => $job->job_status,
													  'job_salary' => $job->salary,
													  'isFavorite'   => $job->isFavoritedByProfile($profile->id)
												 ];
										  })
									 ];
							  })
					);
					
					return responseJson(200, 'Companies retrieved successfully', [
						 'Trending' => $trendingCompanies,
						 'Popular' => $popularCompanies,
					]);
			 }
			 
			 /**
			  * Retrieve details of a specific company.
			  *
			  * @param int $companyId
			  *
			  * @return JsonResponse
			  */
			 public function show(int $companyId): JsonResponse
			 {
					try {
						  $company = Company::with(
								['jobs' => fn($query) => $query->where(
									 'job_status', '!=', 'cancelled'
								)]
						  )->find($companyId);
						  
						  if (!$company) {
								 return responseJson(
									  404, 'Company not found', 'Company not found'
								 );
						  }
						  
						  if (auth('admin')->check()) {
								 $admin = auth('admin')->user();
								 if (!$this->isAdminAuthorizedToShow(
									  $admin, $company
								 )
								 ) {
										return responseJson(
											 403, 'Forbidden',
											 'You do not have permission to view this company'
										);
								 }
						  } elseif (!auth('api')->check()) {
								 return responseJson(
									  403, 'Forbidden',
									  'You do not have permission to view this company'
								 );
						  }
						  
						  $activeJobsCount = $company->jobs()->where(
								'job_status', '!=', 'cancelled'
						  )->count();
						  return responseJson(200, 'Company details retrieved', [
								'company'     => $company,
								'active_jobs' => $activeJobsCount,
								]);
					} catch (\Exception $e) {
						  Log::error('Show company error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Check if an admin is authorized to view a specific company.
			  *
			  * @param mixed   $admin
			  * @param Company $company
			  *
			  * @return bool
			  */
			 private function isAdminAuthorizedToShow(mixed $admin, Company $company
			 ): bool {
					if ($admin->hasRole('super-admin')) {
						  return true;
					}
					if ($admin->id === $company->admin_id) {
						  return true;
					}
					if ($admin->hasAnyRole(['hr', 'coo'])
						 && $admin->company_id === $company->id
					) {
						  return true;
					}
					return false;
			 }
			 
			 /**
			  * Create a new company.
			  *
			  * @param Request $request
			  *
			  * @return JsonResponse
			  */
			 public function store(Request $request): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $validationRules = $this->getStoreValidationRules($admin);
						  $validated = $request->validate(
								$validationRules['rules'], $validationRules['messages']
						  );
						  
						  if (!$admin->hasRole('super-admin') && $admin->company) {
								 return responseJson(
									  403, 'Forbidden',
									  'You already have a company and cannot create another'
								 );
						  }
						  
						  $validated = $this->handleLogoUpload($request, $validated);
						  
						  if ($admin->hasRole('super-admin')) {
								 $targetAdmin = Admin::find($validated['admin_id']);
								 if (!$targetAdmin) {
										return responseJson(
											 404, 'Admin not found', 'Admin not found'
										);
								 }
								 if ($targetAdmin->company) {
										return responseJson(
											 400, 'Admin already has a company',
											 'The specified admin already has a company'
										);
								 }
								 $company = Company::create($validated);
								 $targetAdmin->update(['company_id' => $company->id]);
						  } else {
								 $validated['admin_id'] = $admin->id;
								 $company = Company::create($validated);
								 $admin->update(['company_id' => $company->id]);
						  }
						  
						  User::all()->each(function ($user) use ($company) {
								 $user->notify(
									  new JobizzUserNotification(
											title: 'New Company Added',
											body: "A new company, {$company->name}, has been added to Jobizz.",
											data: ['company' => $company->name]
									  )
								 );
						  });
						  
						  return responseJson(201, 'Company created successfully', [
								'company'      => $company,
								'hired_people' => $company->hired_people,
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Store company error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Get validation rules and messages for storing a company.
			  *
			  * @param mixed $admin
			  *
			  * @return array
			  */
			 private function getStoreValidationRules(mixed $admin): array
			 {
					$rules = [
						 'name'         => 'required|string|max:255|unique:companies',
						 'logo'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
						 'description'  => 'required|string|max:1000',
						 'location'     => 'required|string|max:255',
						 'website'      => 'nullable|url',
						 'size'         => 'nullable|string|max:255',
						 'hired_people' => 'required|numeric|min:5',
					];
					
					if ($admin->hasRole('super-admin')) {
						  $rules['admin_id'] = 'required|exists:admins,id';
					}
					
					return [
						 'rules'    => $rules,
						 'messages' => [
							  'name.unique'      => 'A company with this name already exists',
							  'name.max'         => 'Company name cannot exceed 255 characters',
							  'logo.image'       => 'The logo must be an image',
							  'logo.mimes'       => 'The logo must be a file of type: jpeg, png, jpg, gif, svg',
							  'logo.max'         => 'The logo cannot exceed 2MB in size',
							  'description.max'  => 'Company description cannot exceed 1000 characters',
							  'location.max'     => 'Location cannot exceed 255 characters',
							  'size.max'         => 'Company size cannot exceed 255 characters',
							  'hired_people.min' => 'Hired people count must be at least 5',
						 ],
					];
			 }
			 
			 /**
			  * Handle logo upload for company creation or update.
			  *
			  * @param Request      $request
			  * @param array        $validated
			  * @param Company|null $company
			  *
			  * @return array
			  */
			 private function handleLogoUpload(Request $request, array $validated,
				  ?Company $company = null
			 ): array {
					if ($request->hasFile('logo')) {
						  if ($company && $company->logo
								&& Storage::disk('public')->exists(
									 str_replace(
										  Storage::disk('public')->url(''), '',
										  $company->logo
									 )
								)
						  ) {
								 Storage::disk('public')->delete(
									  str_replace(
											Storage::disk('public')->url(''), '',
											$company->logo
									  )
								 );
						  }
						  $logoPath = $request->file('logo')->store(
								'company_logos', 'public'
						  );
						  $validated['logo'] = Storage::disk('public')->url(
								$logoPath
						  );
					} elseif (!$company || !$company->logo) {
						  $validated['logo']
								= 'https://jobizaa.com/still_images/company.png';
					}
					
					return $validated;
			 }
			 
			 /**
			  * Update an existing company.
			  *
			  * @param Request $request
			  * @param int     $companyId
			  *
			  * @return JsonResponse
			  */
			 public function update(Request $request, int $companyId): JsonResponse
			 {
					try {
						  $admin = auth('admin')->user();
						  if (!$admin) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $company = Company::find($companyId);
						  if (!$company) {
								 return responseJson(
									  404, 'Company not found', 'Company not found'
								 );
						  }
						  
						  $validationRules = $this->getUpdateValidationRules(
								$admin, $company
						  );
						  $validated = $request->validate(
								$validationRules['rules'], $validationRules['messages']
						  );
						  
						  $validated = $this->handleLogoUpload(
								$request, $validated, $company
						  );
						  
						  $originalData = $company->only(array_keys($validated));
						  $changes = array_diff_assoc($validated, $originalData);
						  
						  if (empty($changes)) {
								 return responseJson(200, 'No changes detected', [
									  'company'      => $company,
									  'hired_people' => $company->hired_people,
									  'unchanged'    => true,
								 ]);
						  }
						  
						  if ($admin->hasRole('super-admin')) {
								 $targetAdmin = Admin::find(
									  $validated['admin_id'] ?? null
								 );
								 if (!$targetAdmin) {
										return responseJson(
											 404, 'Admin not found', 'Admin not found'
										);
								 }
								 if ($targetAdmin->company
									  && $targetAdmin->company->id !== $company->id
								 ) {
										return responseJson(
											 400, 'Admin already has a company',
											 'The specified admin already has a company'
										);
								 }
								 $company->update($validated);
								 if ($validated['admin_id'] !== $company->admin_id) {
										$targetAdmin->update(
											 ['company_id' => $company->id]
										);
										Admin::where('company_id', $company->id)->where(
											 'id', '!=', $targetAdmin->id
										)->update(['company_id' => null]);
								 }
						  } else {
								 unset($validated['admin_id']);
								 $company->update($validated);
						  }
						  
						  Cache::forget(
								'admin_companies_page_' . request()->get('page', 1)
						  );
						  Cache::forget('total_companies_count');
						  Cache::forget('trending_companies');
						  Cache::forget('popular_companies');
						  
						  return responseJson(200, 'Company updated successfully', [
								'company'      => $company->fresh(),
								'hired_people' => $company->hired_people,
						  ]);
					} catch (ValidationException $e) {
						  return responseJson(
								422, 'Validation error', $e->validator->errors()->all()
						  );
					} catch (\Exception $e) {
						  Log::error('Update company error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
			 
			 /**
			  * Get validation rules and messages for updating a company.
			  *
			  * @param mixed   $admin
			  * @param Company $company
			  *
			  * @return array
			  * @throws \Exception
			  */
			 private function getUpdateValidationRules(mixed $admin, Company $company
			 ): array {
					$rules = [
						 'logo'         => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
						 'description'  => 'sometimes|string|max:1000',
						 'location'     => 'sometimes|string|max:255',
						 'website'      => 'sometimes|url',
						 'size'         => 'sometimes|string|max:255',
						 'hired_people' => 'sometimes|numeric|min:5',
					];
					
					if ($admin->hasRole('super-admin')) {
						  $rules['admin_id'] = 'sometimes|exists:admins,id';
					} elseif ($admin->id !== $company->admin_id) {
						  throw new \Exception(
								'You can only update your own company'
						  );
					}
					
					return [
						 'rules'    => $rules,
						 'messages' => [
							  'name.unique'      => 'A company with this name already exists',
							  'name.max'         => 'Company name cannot exceed 255 characters',
							  'logo.image'       => 'The logo must be an image',
							  'logo.mimes'       => 'The logo must be a file of type: jpeg, png, jpg, gif, svg',
							  'logo.max'         => 'The logo cannot exceed 2MB in size',
							  'description.max'  => 'Company description cannot exceed 1000 characters',
							  'location.max'     => 'Location cannot exceed 255 characters',
							  'size.max'         => 'Company size cannot exceed 255 characters',
							  'hired_people.min' => 'Hired people count must be at least 5',
						 ],
					];
			 }
			 
			 /**
			  * Delete a company and its associated resources.
			  *
			  * @param int $companyId
			  *
			  * @return JsonResponse
			  */
			 public function destroy(int $companyId): JsonResponse
			 {
					try {
						  if (!auth('admin')->check()) {
								 return responseJson(
									  401, 'Unauthenticated', 'Unauthenticated'
								 );
						  }
						  
						  $admin = auth('admin')->user();
						  $company = Company::find($companyId);
						  
						  if (!$company) {
								 return responseJson(
									  404, 'Company not found', 'Company not found'
								 );
						  }
						  
						  if (!$admin->hasPermissionTo('manage-all-companies')
								&& !($admin->hasPermissionTo('manage-own-company')
									 && $company->id === $admin->company_id)
						  ) {
								 return responseJson(
									  403, 'Forbidden',
									  'You do not have permission to delete this company'
								 );
						  }
						  
						  $company->jobs()->where('job_status', '!=', 'cancelled')
								->update(['job_status' => 'cancelled']);
						  
						  $subAdmins = Admin::where('company_id', $company->id)
								->where('id', '!=', $admin->id)->get();
						  foreach ($subAdmins as $subAdmin) {
								 if ($subAdmin->photo
									  && Storage::disk('public')->exists(
											$subAdmin->photo
									  )
								 ) {
										Storage::disk('public')->delete($subAdmin->photo);
								 }
						  }
						  
						  Admin::where('company_id', $company->id)
								->where('id', '!=', $admin->id)
								->delete();
						  
						  Admin::where('company_id', $company->id)->update(
								['company_id' => null]
						  );
						  
						  if ($company->logo
								&& Storage::disk('public')->exists(
									 str_replace(
										  Storage::disk('public')->url(''), '',
										  $company->logo
									 )
								)
						  ) {
								 Storage::disk('public')->delete(
									  str_replace(
											Storage::disk('public')->url(''), '',
											$company->logo
									  )
								 );
						  }
						  
						  $company->delete();
						  
						  Cache::forget(
								'admin_companies_page_' . request()->get('page', 1)
						  );
						  Cache::forget('total_companies_count');
						  Cache::forget('trending_companies');
						  Cache::forget('popular_companies');
						  
						  return responseJson(
								200,
								'Company and associated resources deleted successfully'
						  );
					} catch (\Exception $e) {
						  Log::error('Destroy company error: ' . $e->getMessage());
						  return responseJson(
								500, 'Server error',
								config('app.debug') ? $e->getMessage() : null
						  );
					}
			 }
	  }