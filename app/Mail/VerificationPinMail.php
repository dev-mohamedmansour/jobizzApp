<?php
	  
	  namespace App\Mail;
	  
	  use Illuminate\Bus\Queueable;
	  use Illuminate\Mail\Mailable;
	  use Illuminate\Queue\SerializesModels;
	  
	  class VerificationPinMail extends Mailable
	  {
			 use Queueable, SerializesModels;
			 
			 public $pin;
			 
			 public function __construct($pin)
			 {
					$this->pin = $pin;
			 }
			 
			 public function build()
			 {
					return $this->subject('Your Email Verification PIN')
						 ->view('emails.verification_pin', ['pin' => $this->pin]);
			 }
	  }