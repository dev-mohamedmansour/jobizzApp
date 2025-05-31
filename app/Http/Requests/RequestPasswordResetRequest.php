<?php
	  
	  namespace App\Http\Requests;
	  
	  use Illuminate\Foundation\Http\FormRequest;
	  
	  class RequestPasswordResetRequest extends FormRequest
	  {
			 public function authorize(): bool
			 {
					return true; // Allow all users to request password reset
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
					];
			 }
	  }