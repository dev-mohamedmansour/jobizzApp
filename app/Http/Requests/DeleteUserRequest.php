<?php
	  
	  namespace App\Http\Requests;
	  
	  use Illuminate\Foundation\Http\FormRequest;
	  
	  class DeleteUserRequest extends FormRequest
	  {
			 public function authorize(): bool
			 {
					// Only authenticated admins with a super-admin role can delete users
					return auth('admin')->check() && auth('admin')->user()->hasRole('super-admin');
			 }
			 
			 public function rules(): array
			 {
					return [
						 'id' => 'required|integer|exists:users,id',
					];
			 }
			 
			 public function messages(): array
			 {
					return [
						 'id.required' => 'The user ID is required.',
						 'id.integer' => 'The user ID must be an integer.',
						 'id.exists' => 'The specified user does not exist.',
					];
			 }
	  }