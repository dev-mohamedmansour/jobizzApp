<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Models\Admin;
	  use App\Models\Company;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Http\Request;
	  
	  
	  class CompanyController extends Controller
	  {
			 
			 public function index(): JsonResponse
			 {
					try {
						  // Check authenticated user
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $companies = Company::withCount('jobs')->paginate(10);
						  
						  if ($companies->isEmpty()) {
								 return responseJson(404, 'No companies found');
						  }
						  
						  return responseJson(200, 'Companies retrieved successfully', $companies);
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 
			 public function show($id): JsonResponse
			 {
					try {
						  // Check authenticated user
						  if (!auth()->check()) {
								 return responseJson(401, 'Unauthenticated');
						  }
						  
						  $company = Company::find($id);
						  
						  if (!$company) {
								 return responseJson(404, 'Company not found');
						  }
						  
						  // Get active jobs for this company
						  $activeJobs = $company->jobs()
								->where('company_id', $company->id)
								->activeJobs()
								->count();
						  
						  return responseJson(200, 'Company details retrieved', [
								'company' => $company,
								'active_jobs' => $activeJobs
						  ]);
						  
					} catch (\Exception $e) {
						  return responseJson(500, 'Server error', [
								'error' => config('app.debug') ? $e->getMessage() : null
						  ]);
					}
			 }
			 public function store(Request $request): \Illuminate\Http\JsonResponse
			 {
					/** @var Admin $admin */
					
					$admin = auth('admin')->user();
					
					if ($admin->hasRole('super-admin')) {
						  $validated = $request->validate([
								'name' => 'required|unique:companies',
								'admin_id' => 'required|exists:admins,id'
						  ]);
						  
						  $company = Company::create($validated);
					} else {
						  if (!$admin->hasPermissionTo('manage-own-company')) {
								 return responseJson(403, 'Unauthorized');
						  }
						  
						  $validated = $request->validate([
								'name' => 'required|unique:companies'
						  ]);
						  
						  $company = $admin->company()->create($validated);
					}
					
					return responseJson(201, 'Company created', $company);
			 }
			 
			 public function update(Request $request, Company $company): \Illuminate\Http\JsonResponse
			 {
					/** @var Admin $admin */
					
					$admin = auth('admin')->user();
					
					if ($admin->hasPermissionTo('manage-all-companies')) {
						  $company->update($request->all());
					} elseif ($admin->hasPermissionTo('manage-own-company')
						 && $company->id === $admin->company_id
					) {
						  $company->update($request->all());
					} else {
						  return responseJson(403, 'Unauthorized');
					}
					
					return responseJson(200, 'Company updated', $company);
			 }
			 
			 public function destroy(Company $company): \Illuminate\Http\JsonResponse
			 {
					/** @var Admin $admin */
					
					$admin = auth('admin')->user();
					
					if ($admin->hasPermissionTo('manage-all-companies')) {
						  $company->delete();
					} elseif ($admin->hasPermissionTo('manage-own-company')
						 && $company->id === $admin->company_id
					) {
						  $company->delete();
					} else {
						  return responseJson(403, 'Unauthorized');
					}
					
					return responseJson(200, 'Company deleted');
			 }
	  }