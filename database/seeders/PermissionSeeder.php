<?php
	  
	  namespace Database\Seeders;
	  
	  use Illuminate\Database\Seeder;
	  use Spatie\Permission\Models\Permission;
	  use Spatie\Permission\Models\Role;
	  
	  class PermissionSeeder extends Seeder
	  {
			 public function run(): void
			 {
					$permissions = [
						  // Company Permissions
						  'manage-all-companies',
						  'manage-own-company',
						  
						  // Job Permissions
						  'manage-all-jobs',
						  'manage-company-jobs',
						  
						  // Admin Permissions
						  'manage-roles',
						  'manage-company-admins',
						  
						  // Application Permissions
						  'manage-applications',
						  'view-applicant-profiles',
						  'send-messages'
					];
					
					foreach ($permissions as $perm) {
						  Permission::firstOrCreate(['name' => $perm]);
					}
					
					$superAdmin = Role::create(['name' => 'super-admin'])
						 ->syncPermissions(Permission::all());
					
					$admin = Role::create(['name' => 'admin'])
						 ->syncPermissions([
							  'manage-own-company',
							  'manage-company-jobs',
							  'manage-company-admins'
						 ]);
					
					$hr = Role::create(['name' => 'hr'])
						 ->syncPermissions(['manage-company-jobs', 'view-applicant-profiles']);
					
					$coo = Role::create(['name' => 'coo'])
						 ->syncPermissions(['manage-applications', 'send-messages']);
			 }
	  }
