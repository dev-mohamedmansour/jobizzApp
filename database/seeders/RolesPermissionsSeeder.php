<?php
	  
	  namespace Database\Seeders;
	  
	  use App\Models\Permission;
	  use App\Models\Role;
	  use Illuminate\Database\Seeder;
	  
	  class RolesPermissionsSeeder extends Seeder
	  {
			 /**
			  * Run the database seeds.
			  */
			 // database/seeders/RolesPermissionsSeeder.php
			 public function run(): void
			 {
					// Create roles
					$roles = ['super-admin', 'admin', 'hr', 'coo'];
					foreach ($roles as $roleName) {
						  Role::firstOrCreate(['name' => $roleName]);
					}
					
					// Create permissions
					$permissions = [
						 'manage-companies',
						 'manage-jobs',
						 'manage-applications',
						 'manage-users'
					];
					foreach ($permissions as $permissionName) {
						  Permission::firstOrCreate(['name' => $permissionName]);
					}
					
					// Assign all permissions to super-admin
					$superAdmin = Role::where('name', 'super-admin')->first();
					$superAdmin->permissions()->sync(Permission::all());
			 }
	  }