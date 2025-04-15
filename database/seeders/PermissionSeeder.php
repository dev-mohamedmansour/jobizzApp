<?php
	  
	  namespace Database\Seeders;
	  
	  use Illuminate\Database\Seeder;
	  use Spatie\Permission\Models\Permission;
	  use Spatie\Permission\Models\Role;
	  
	  class PermissionSeeder extends Seeder
	  {
			 /**
			  * Run the database seeds.
			  */
			 public function run(): void
			 {
					// Permissions
					$permissions = [
						 'manage-all-companies',
						 'manage-roles',
						 'manage-admin-users',
						 'manage-own-company',
						 'manage-company-jobs',
						 'manage-company-admins',
						 'manage-applications',
						 'send-messages',
						 'view-applicants'
					];
					
					foreach ($permissions as $perm) {
						  Permission::create(['name' => $perm]);
					}
					
					// Roles
					$superAdmin = Role::create(['name' => 'super-admin']);
					$superAdmin->syncPermissions(Permission::all());
					
					$admin = Role::create(['name' => 'admin']);
					$admin->syncPermissions(
						 ['manage-own-company', 'manage-company-jobs',
						  'manage-company-admins']
					);
					$hr = Role::create(['name' => 'hr']);
					$hr->syncPermissions(['manage-company-jobs', 'view-applicants']);
					
					$coo = Role::create(['name' => 'coo']);
					$coo->syncPermissions(['manage-applications', 'send-messages']);
			 }
	  }
