<?php
	  
	  namespace App\Mail;
	  
	  use Illuminate\Bus\Queueable;
	  use Illuminate\Mail\Mailable;
	  use Illuminate\Queue\SerializesModels;
	  
	  class PinNotificationMail extends Mailable
	  {
			 use Queueable, SerializesModels;
			 
			 public $pin;
			 public $type; // 'verification' or 'reset'
			 public $expiryMinutes;
			 
			 public function __construct($pin, $type, $expiryMinutes)
			 {
					$this->pin = $pin;
					$this->type = $type;
					$this->expiryMinutes = $expiryMinutes;
			 }
			 
			 public function build()
			 {
					return $this->subject($this->getSubject())
						 ->markdown('emails.auth.pin_notification');
			 }
			 
			 protected function getSubject()
			 {
					return $this->type === 'verification'
						 ? 'Verify Your Email Address'
						 : 'Your Password Reset Code';
			 }
	  }