<?php
	  
	  namespace App\Http\Controllers\Admin;
	  
	  use App\Models\Company;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\Gate;
	  
	  class CompanyController extends Controller
	  {
			 // Get all companies (Super-Admin only)
			 public function index()
			 {
					try {
						  $this->authorize('viewAny', Company::class);
						  
						  $companies = Cache::remember('all_companies', 3600, function () {
								 return Company::with('admins')->get();
						  });
						  
						  return responseJson(200, 'Companies retrieved', $companies);
					} catch (\Exception $e) {
						  return responseJson(403, 'Unauthorized');
					}
			 }
			 
			 // Create new company (Super-Admin/Admin with approval)
			 public function store(Request $request)
			 {
					try {
						  $validated = $request->validate([
								'name' => 'required|unique:companies|max:255',
								'industry' => 'required|in:'.implode(',', config('app.industries')),
								'website' => 'required|url'
						  ]);
						  
						  if (auth('admin')->user()->role->name !== 'super-admin') {
								 $this->requireApproval('company.create', $validated);
								 return responseJson(202, 'Company creation pending approval');
						  }
						  
						  $company = Company::create($validated + ['admin_id' => auth('admin')->id()]);
						  
						  ActivityLog::create([
								'admin_id' => auth('admin')->id(),
								'action' => 'company.create',
								'details' => $company
						  ]);
						  
						  return responseJson(201, 'Company created', $company);
						  
					} catch (ValidationException $e) {
						  return responseJson(422, 'Validation failed', $e->errors());
					}
			 }
			 
			 // Update company (Owner Admin/Super-Admin)
			 public function update(Request $request, Company $company)
			 {
					try {
						  $this->authorize('update', $company);
						  
						  $validated = $request->validate([
								'name' => 'sometimes|max:255',
								'industry' => 'sometimes|in:'.implode(',', config('app.industries'))
						  ]);
						  
						  if (auth('admin')->user()->role->name !== 'super-admin') {
								 $this->requireApproval('company.update', [
									  'company_id' => $company->id,
									  'changes' => $validated
								 ]);
								 return responseJson(202, 'Update pending approval');
						  }
						  
						  $company->update($validated);
						  
						  Redis::del('company:'.$company->id); // Clear cache
						  
						  return responseJson(200, 'Company updated', $company);
						  
					} catch (AuthorizationException $e) {
						  return responseJson(403, 'Unauthorized');
					}
			 }
			 
			 // Delete company (Super-Admin/Owner with approval)
			 public function destroy(Company $company)
			 {
					try {
						  $this->authorize('delete', $company);
						  
						  if (auth('admin')->user()->role->name !== 'super-admin') {
								 $this->requireApproval('company.delete', [
									  'company_id' => $company->id
								 ]);
								 return responseJson(202, 'Deletion pending approval');
						  }
						  
						  $company->delete();
						  
						  event(new CompanyDeleted($company));
						  
						  return responseJson(200, 'Company deleted');
						  
					} catch (AuthorizationException $e) {
						  return responseJson(403, 'Unauthorized');
					}
			 }
			 
			 private function requireApproval($action, $data)
			 {
					Approval::create([
						 'admin_id' => auth('admin')->id(),
						 'action' => $action,
						 'data' => json_encode($data),
						 'status' => 'pending'
					]);
			 }