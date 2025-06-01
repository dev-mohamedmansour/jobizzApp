<?php
	  
	  namespace App\Http\Requests;
	  
	  use Illuminate\Foundation\Http\FormRequest;
	  
	  class AdminRegistrationRequest extends FormRequest
	  {
			 public function authorize(): bool
			 {
					return true; // Allow all users to register; adjust if you need specific authorization
			 }
			 
			 public function rules(): array
			 {
					return [
						 'fullName' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
						 'email' => [
							  'required',
							  'string',
							  'email',
							  'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
							  'unique:admins,email',
							  'unique:users,email',
							  'ascii',
						 ],
						 'photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
						 'phone' => 'required|string|max_digits:11|unique:admins,phone',
						 'password' => [
							  'required',
							  'string',
							  'min:8',
							  'confirmed',
							  'regex:/^[a-zA-Z0-9@#$%^&*!]+$/',
							  'regex:/[a-zA-Z]/',
							  'regex:/[0-9]/',
							  'regex:/[@#$%^&*!]/',
						 ],
					];
			 }
			 
			 public function messages(): array
			 {
					return [
						 'fullName.required' => 'The name field is required.',
						 'fullName.regex' => 'Name must contain only English letters and spaces.',
						 'email.required' => 'The email field is required.',
						 'email.regex' => 'Invalid email format. Please use English characters only.',
						 'email.ascii' => 'Email must contain only English characters.',
						 'email.unique' => 'This email is already registered.',
						 'photo.image' => 'The photo must be an image.',
						 'photo.mimes' => 'The photo must be a file of type: jpeg, png, jpg, gif, svg.',
						 'photo.max' => 'The photo cannot exceed 2MB in size.',
						 'phone.required' => 'The phone field is required.',
						 'phone.max_digits' => 'The phone number cannot exceed 11 digits.',
						 'phone.unique' => 'This phone number is already registered.',
						 'password.required' => 'The password field is required.',
						 'password.confirmed' => 'Password confirmation does not match.',
						 'password.min' => 'Password must be at least 8 characters.',
						 'password.regex' => 'Password contains invalid characters. Use only English letters, numbers, and special symbols.',
					];
			 }
	  }