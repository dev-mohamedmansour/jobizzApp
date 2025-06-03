<?php
	  
	  namespace App\Http\Requests;
	  
	  use Illuminate\Foundation\Http\FormRequest;
	  
	  class ListUsersRequest extends FormRequest
	  {
			 public function authorize(): bool
			 {
					// Only authenticated admins with appropriate permissions can list users
					return auth('admin')->check() && auth('admin')->user()->hasRole('super-admin');
			 }
			 
			 public function rules(): array
			 {
					return [
						 'per_page' => 'sometimes|integer|min:1|max:100',
						 'search' => 'sometimes|string|max:255',
						 'sort_by' => 'sometimes|in:id,email,name,created_at',
						 'sort_direction' => 'sometimes|in:asc,desc',
					];
			 }
			 
			 public function messages(): array
			 {
					return [
						 'per_page.integer' => 'The per_page value must be an integer.',
						 'per_page.min' => 'The per_page value must be at least 1.',
						 'per_page.max' => 'The per_page value cannot exceed 100.',
						 'search.string' => 'The search term must be a string.',
						 'search.max' => 'The search term cannot exceed 255 characters.',
						 'sort_by.in' => 'The sort_by field must be one of: id, email, name, created_at.',
						 'sort_direction.in' => 'The sort_direction must be either asc or desc.',
					];
			 }
	  }