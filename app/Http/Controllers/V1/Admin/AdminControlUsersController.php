<?php
	  
	  namespace App\Http\Controllers\V1\Admin;
	  
	  use App\Http\Controllers\Controller;
	  use App\Http\Requests\ListUsersRequest;
	  use App\Http\Requests\DeleteUserRequest;
	  use App\Models\User;
	  use Illuminate\Http\JsonResponse;
	  use Illuminate\Support\Facades\DB;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Storage;
	  
	  class AdminControlUsersController extends Controller
	  {
			 /**
			  * List users with pagination, filtering, and sorting.
			  */
			 public function index(ListUsersRequest $request): JsonResponse
			 {
					try {
						  $query = User::query();
						  
						  // Apply search filter
						  if ($search = $request->input('search')) {
								 $query->where(function ($q) use ($search) {
										$q->where('name', 'like', "%{$search}%")
											 ->orWhere('email', 'like', "%{$search}%");
								 });
						  }
						  
						  // Apply sorting
						  $sortBy = $request->input('sort_by', 'created_at');
						  $sortDirection = $request->input('sort_direction', 'desc');
						  $query->orderBy($sortBy, $sortDirection);
						  
						  // Paginate results
						  $perPage = $request->input('per_page', 10);
						  $users = $query->paginate($perPage);
						  
						  if ($users->isEmpty()) {
								 return responseJson(404, 'No users found');
						  }
						  
						  return responseJson(200, 'Users retrieved successfully', [
								'users' => $users->items(),
								'pagination' => [
									 'total' => $users->total(),
									 'per_page' => $users->perPage(),
									 'current_page' => $users->currentPage(),
									 'last_page' => $users->lastPage(),
								],
						  ]);
					} catch (\Exception $e) {
						  Log::error('User Listing Error', [
								'message' => $e->getMessage(),
								'admin_id' => auth('admin')->id() ?? 'nothing',
								'request' => $request->all(),
						  ]);
						  $errorMessage = config('app.debug') ? $e->getMessage() : 'Server error: Something went wrong.';
						  return responseJson(500, 'Server error', $errorMessage);
					}
			 }
			 
			 /**
			  * Delete a user and their associated data.
			  */
			 public function destroy(DeleteUserRequest $request, $id): JsonResponse
			 {
					try {
						  $user = User::findOrFail($id);
						  
						  DB::transaction(function () use ($user) {
								 // Delete associated profiles and their data
								 foreach ($user->profiles as $profile) {
										// Delete educations and their images
										$this->deleteFilesAndRecords($profile->educations, 'image');
										
										// Delete experiences and their images
										$this->deleteFilesAndRecords($profile->experiences, 'image');
										
										// Delete documents and their files
										$this->deleteFilesAndRecords($profile->documents, 'file');
										
										// Delete profile image
										if ($profile->profile_image && Storage::disk('public')->exists($profile->profile_image)) {
											  Storage::disk('public')->delete($profile->profile_image);
										}
										// Delete the profile
										$profile->delete();
								 }
								 
								 // Delete the user
								 $user->delete();
						  });
						  
						  return responseJson(200, 'User and associated data deleted successfully');
					} catch (\Exception $e) {
						  Log::error('User Deletion Error', [
								'message' => $e->getMessage(),
								'admin_id' => auth('admin')->id() ?? 'nothing',
								'user_id' => $id,
						  ]);
						  $errorMessage = config('app.debug') ? $e->getMessage() : 'Server error: Something went wrong.';
						  return responseJson(500, 'Server error', $errorMessage);
					}
			 }
			 
			 /**
			  * Delete records and their associated files.
			  */
			 private function deleteFilesAndRecords($records, string $fileField): void
			 {
					if ($records) {
						  foreach ($records as $record) {
								 if ($record->$fileField && Storage::disk('public')->exists($record->$fileField)) {
										Storage::disk('public')->delete($record->$fileField);
								 }
						  }
						  $records->delete();
					}
			 }
	  }