<?php
	  
	  namespace App\Notifications;
	  
	  use Illuminate\Bus\Queueable;
	  use Illuminate\Contracts\Queue\ShouldQueue;
	  use Illuminate\Notifications\Notification;
	  use Kreait\Firebase\Messaging\CloudMessage;
	  use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
	  
	  class JobizzUserNotification extends Notification implements ShouldQueue
	  {
			 use Queueable;
			 
			 protected string $title;
			 protected string $body;
			 protected array $data;
			 
			 public function __construct(string $title, string $body, array $data = [])
			 {
					$this->title = $title;
					$this->body = $body;
					$this->data = $data;
			 }
			 
			 public function via($notifiable): array
			 {
					return ['database', 'fcm'];
			 }
			 
			 public function toArray($notifiable): array
			 {
					return [
						 'title' => $this->title,
						 'body' => $this->body,
						 'data' => $this->data,
						 'created_at' => now()->toDateTimeString(),
					];
			 }
			 
			 public function toFcm($notifiable): ?CloudMessage
			 {
					if (!$notifiable->fcm_token) {
						  return null;
					}
					
					return CloudMessage::withTarget('token', $notifiable->fcm_token)
						 ->withNotification(FirebaseNotification::create($this->title, $this->body))
						 ->withData($this->data);
			 }
	  }