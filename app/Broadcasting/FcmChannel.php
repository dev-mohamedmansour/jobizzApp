<?php

namespace App\Broadcasting;
use Illuminate\Notifications\Notification;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;

class FcmChannel
{
	  protected Messaging $messaging;
	  
	  public function __construct(Messaging $messaging)
	  {
			 $this->messaging = $messaging;
	  }
	  
	  /**
		* @throws MessagingException
		* @throws FirebaseException
		*/
	  public function send($notifiable, Notification $notification): void
	  {
			 $message = $notification->toFcm($notifiable);
			 
			 if ($message) {
					$this->messaging->send($message);
			 }
	  }
}
