<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CompanyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
	  public function viewAny(Admin $admin)
	  {
			 return $admin->role->name === 'super-admin';
	  }

	  public function update(Admin $admin, Company $company)
	  {
			 return $admin->role->name === 'super-admin' ||
				  ($admin->company_id === $company->id && $admin->hasPermission('manage-companies'));
	  }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Company $company): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Company $company): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Company $company): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Company $company): bool
    {
        return false;
    }
}
