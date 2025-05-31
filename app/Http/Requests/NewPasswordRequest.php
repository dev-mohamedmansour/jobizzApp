<?php
	  
	  namespace App\Http\Requests;
	  
	  use Illuminate\Foundation\Http\FormRequest;
	  
	  class NewPasswordRequest extends FormRequest
	  {
			 public function authorize(): bool
			 {
					return true; // Allow all users to reset password with valid PIN
			 }
			 
			 public function rules(): array
			 {
					return [
						 'email' => [
							  'required',
							  'string',
							  'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
							  'email',
							  'exists:admins,email',
							  'ascii',
						 ],
						 'pinCode' => [
							  'required',
							  'digits:6',
							  'numeric',
							  'not_in:000000,111111,123456,654321',
						 ],
						 'newPassword' => 'required|string|min:8|confirmed|regex:/^[a-zA-Z0-9@#$%^&*!]+$/',
					];
			 }
			 
			 public function messages(): array
			 {
					return [
						 'email.required' => 'The email field is required.',
						 'email.email' => 'Please enter a valid email address.',
						 'email.exists' => 'Account not found in app.',
						 'email.regex' => 'Invalid email format. Please use English characters only.',
						 'email.ascii' => 'Email must contain only English characters.',
						 'pinCode.required' => 'PIN code is required.',
						 'pinCode.digits' => 'PIN must be exactly 6 digits.',
						 'pinCode.numeric' => 'PIN must contain only numbers.',
						 'pinCode.not_in' => 'This PIN is too common and insecure.',
						 'newPassword.required' => 'New password is required.',
						 'newPassword.confirmed' => 'Password confirmation does not match.',
						 'newPassword.min' => 'Password must be at least 8 characters.',
						 'newPassword.regex' => 'Password contains invalid characters. Use only English letters, numbers, and special symbols.',
					];
			 }
	  }