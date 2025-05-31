<?php
	  
	  namespace App\Http\Requests;
	  
	  use Illuminate\Foundation\Http\FormRequest;
	  
	  class VerifyEmailRequest extends FormRequest
	  {
			 public function authorize(): bool
			 {
					return true; // Allow all users to verify email
			 }
			 
			 public function rules(): array
			 {
					return [
						 'email' => [
							  'required',
							  'string',
							  'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
							  'exists:admins,email',
							  'ascii',
						 ],
						 'pin_code' => [
							  'required',
							  'digits:6',
							  'numeric',
							  'not_in:000000,111111,123456,654321',
						 ],
					];
			 }
			 
			 public function messages(): array
			 {
					return [
						 'email.required' => 'The email field is required.',
						 'email.regex' => 'Invalid email format. Please use English characters only.',
						 'email.ascii' => 'Email must contain only English characters.',
						 'email.exists' => 'This email is not registered.',
						 'pin_code.required' => 'PIN code is required.',
						 'pin_code.digits' => 'PIN must be exactly 6 digits.',
						 'pin_code.numeric' => 'PIN must contain only numbers.',
						 'pin_code.not_in' => 'This PIN is too common and insecure.',
					];
			 }
	  }