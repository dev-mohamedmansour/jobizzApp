<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
	  public function index(): JsonResponse
	  {
			 try {
					// Check if the user is authenticated
					if (!auth('admin')->check()) {
						  return responseJson(401, 'Unauthenticated');
					}
					
					// Determine which guard the user is authenticated with
					if (auth()->guard('admin')->check()) {
						  $user = auth('admin')->user();
						  if (!$this->isAdminAuthorized($user)) {
								 return responseJson(
									  403,
									  'Forbidden: You do not have permission to view this company'
								 );
						  }
					} elseif (auth()->guard('api')->check()) {
						  // Deny access if the user is authenticated with an unknown guard
						  return responseJson(
								403,
								'Forbidden: You do not have permission to view this company'
						  );
					}
					
					$users = User::paginate(10);
					
					if ($users->isEmpty()) {
						  return responseJson(404, 'No users found');
					}
					
					return responseJson(
						 200, 'users retrieved successfully', ['users' => $users->items()]
					);
					
			 } catch (\Exception $e) {
					return responseJson(500, 'Server error', [
						 'error' => config('app.debug') ? $e->getMessage() : null
					]);
			 }
	  }
	  private function isAdminAuthorized($admin): bool
	  {
			 // Check if the user is a super-admin
			 if ($admin->hasRole('super-admin')) {
					return true;
			 }
			 return false;
	  }
	  
}
