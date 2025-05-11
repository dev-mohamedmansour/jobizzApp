<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
	  public function index(Request $request): JsonResponse
	  {
			 try {
					// Check if the user is authenticated
					if (!auth('admin')->check()) {
						  return responseJson(401, 'Unauthenticated','Unauthenticated');
					}
					// Determine which guard the user is authenticated with
					if (auth('admin')->check()) {
						  $user = auth('admin')->user();
//						  dd($user);
						  if (!$this->isAdminAuthorized($user)) {
								 return responseJson(
									  403,
									  'Forbidden','You do not have permission to view users '
								 );
						  }
					} elseif (auth()->guard('api')->check()) {
						  // Deny access if the user is authenticated with an unknown guard
						  return responseJson(
								403,
								'Forbidden','You do not have permission to view this users'
						  );
					}
					
					$users = User::paginate(10);
					
					if ($users->isEmpty()) {
						  return responseJson(404,'Error','No users found');
					}
					
					return responseJson(
						 200, 'users retrieved successfully', ['users' => $users->items()]
					);
					
			 } catch (\Exception $e) {
					return responseJson(500, 'Server error',
						 config('app.debug') ? $e->getMessage() : null
					);
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
	  public function destroy($id): JsonResponse
	  {
			 try {
					// Check if the user is authenticated
					if (!auth('admin')->check()) {
						  return responseJson(401, 'Unauthenticated','Unauthenticated');
					}
					
					$admin = auth('admin')->user();
					$user = User::find($id);
					
					// Check if the user exists
					if (!$user) {
						  return responseJson(404,'Error','User not found');
					}
					
					// Determine authorization
					if (!$admin->hasPermissionTo('manage-all-companies')) {
						  return responseJson(
								403,
								'Forbidden','You do not have permission to delete this user'
						  );
					}
					
					// Delete all profiles and their associated data
					$profiles = $user->profiles;
					foreach ($profiles as $profile) {
						  // Delete educations and their images
						  if ($profile->educations) {
								 foreach ($profile->educations as $education) {
										if ($education->image && Storage::disk('public')->exists($education->image)) {
											  Storage::disk('public')->delete($education->image);
										}
								 }
								 $profile->educations()->delete();
						  }
						  
						  // Delete experiences and their images
						  if ($profile->experiences) {
								 foreach ($profile->experiences as $experience) {
										if ($experience->image && Storage::disk('public')->exists($experience->image)) {
											  Storage::disk('public')->delete($experience->image);
										}
								 }
								 $profile->experiences()->delete();
						  }
						  
						  // Delete documents and their files
						  if ($profile->documents) {
								 foreach ($profile->documents as $document) {
										if ($document->file && Storage::disk('public')->exists($document->file)) {
											  Storage::disk('public')->delete($document->file);
										}
								 }
								 $profile->documents()->delete();
						  }
						  
						  // Delete the profile image if it exists
						  if ($profile->profile_image && Storage::disk('public')->exists($profile->profile_image)) {
								 Storage::disk('public')->delete($profile->profile_image);
						  }
						  
						  // Delete the profile
						  $profile->delete();
					}
					
					// Delete the user
					$user->delete();
					
					return responseJson(
						 200,
						 'User and associated profiles and documents deleted successfully'
					);
					
			 } catch (\Exception $e) {
					// Handle exceptions
					Log::error('Server Error: ' . $e->getMessage());
					$errorMessage = config('app.debug') ? $e->getMessage() : 'Server error: Something went wrong. Please try again later.';
					return responseJson(500,'Error',$errorMessage);
			 }
	  }
}
