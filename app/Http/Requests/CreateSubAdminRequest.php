<?php
	  
	  namespace App\Http\Requests;
	  
	  use Illuminate\Foundation\Http\FormRequest;
	  
	  class CreateSubAdminRequest extends FormRequest
	  {
			 public function authorize(): bool
			 {
					return auth('admin')->check() && auth('admin')->user()->hasPermissionTo('manage-company-admins');
			 }
			 
			 public function rules(): array
			 {
					return [
						 'fullName' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
						 'email' => [
							  'required',
							  'string',
							  'email',
							  'max:255',
							  'unique:admins,email',
							  'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
							  'ascii',
						 ],
						 'phone' => 'required|string|max_digits:11|unique:admins,phone',
						 'role' => 'required|in:hr,coo',
					];
			 }
			 
			 public function messages(): array
			 {
					return [
						 'fullName.required' => 'The name field is required.',
						 'fullName.regex' => 'Name must only contain letters and spaces.',
						 'email.required' => 'The email field is required.',
						 'email.email' => 'Please enter a valid email address.',
						 'email.unique' => 'This email is already registered.',
						 'email.regex' => 'Invalid email format. Please use English characters only.',
						 'email.ascii' => 'Email must contain only English characters.',
						 'phone.required' => 'The phone field is required.',
						 'phone.max_digits' => 'The phone number cannot exceed 11 digits.',
						 'phone.unique' => 'This phone number is already registered.',
						 'role.required' => 'The role field is required.',
						 'role.in' => 'The role must be either hr or coo.',
					];
			 }
	  }