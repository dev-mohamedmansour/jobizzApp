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
						  'send-messages',
						  
						  //pending permission
						  'access-pending'
					];
					
					foreach ($permissions as $permission) {
						  Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
					}
					
					$roles = [
						 'super-admin' => ['manage-all-companies', 'manage-all-jobs', 'manage-roles', 'manage-company-admins', 'manage-applications', 'view-applicant-profiles', 'send-messages'],
						 'admin' => ['manage-applications', 'send-messages','manage-own-company','view-applicant-profiles', 'manage-company-jobs', 'manage-company-admins'],
						 'hr' => ['manage-company-jobs', 'view-applicant-profiles'],
						 'coo' => ['manage-applications', 'send-messages'],
						 'pending' => ['access-pending'],
					];
					
					foreach ($roles as $roleName => $permissions) {
						  $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'admin']);
						  $role->syncPermissions($permissions);
					}
					
//					foreach ($permissions as $perm) {
//						  Permission::firstOrCreate(['name' => $perm,'guard_name' => 'admin']);
//					}
//
//					$superAdmin = Role::create(['name' => 'super-admin', 'guard_name' => 'admin'])
//						 ->syncPermissions(Permission::all());
//
//					$admin = Role::create(['name' => 'admin', 'guard_name' => 'admin'])
//						 ->syncPermissions([
//							  'manage-own-company',
//							  'manage-company-jobs',
//							  'manage-company-admins'
//						 ]);
//
//					$hr = Role::create(['name' => 'hr', 'guard_name' => 'admin'])
//						 ->syncPermissions(['manage-company-jobs', 'view-applicant-profiles']);
//
//					$coo = Role::create(['name' => 'coo', 'guard_name' => 'admin'])
//						 ->syncPermissions(['manage-applications', 'send-messages']);
//
//					$pendingAdmin = Role::create(['name' => 'pending', 'guard_name' => 'admin'])
//						 ->syncPermissions('access-pending');
			 }
	  }
