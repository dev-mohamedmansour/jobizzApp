<?php
	  
	  namespace App\Mail;
	  
	  use Illuminate\Bus\Queueable;
	  use Illuminate\Mail\Mailable;
	  use Illuminate\Queue\SerializesModels;
	  
	  class SubAdminCredentialsMail extends Mailable
	  {
			 use Queueable, SerializesModels;
			 
//			 public $name;
//			 public $email;
//			 public $password;
//			 public $role;
			 
			 public function __construct(
				  public string $name,
				  public string $email,
				  public string $password,
				  public string $role
			 ) {
			 }
			 
			 public function build()
			 {
					return $this->subject('Your Sub-admin Credentials')
						 ->view('emails.sub-admin-credentials');
			 }
	  }