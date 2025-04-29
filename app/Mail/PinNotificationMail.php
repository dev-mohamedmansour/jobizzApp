<?php
	  
	  namespace App\Mail;
	  
	  use Illuminate\Bus\Queueable;
	  use Illuminate\Mail\Mailable;
	  use Illuminate\Queue\SerializesModels;
	  
	  class PinNotificationMail extends Mailable
	  {
			 use Queueable, SerializesModels;
			 
			 public string $pin;
			 public string $type; // 'verification' or 'reset'
			 public string $expiryMinutes;
			 public string $name;
			 
			 public function __construct($pin, $type, $expiryMinutes,$name)
			 {
					$this->pin = $pin;
					$this->type = $type;
					$this->expiryMinutes = $expiryMinutes;
					$this->name = $name;
			 }
			 
			 public function build(): PinNotificationMail
			 {
					return $this->subject($this->getSubject())
						 ->view('emails.auth.pin_notification');
			 }
			 
			 protected function getSubject(): string
			 {
					return $this->type === 'verification'
						 ? 'Verify Your Email Address'
						 : 'Your Password Reset Code';
			 }
	  }