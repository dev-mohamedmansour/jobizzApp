<?php

namespace App\Notifications;

use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminRegistrationPending extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
	  public function __construct(public Admin $admin) {}
    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
	  
	  public function via($notifiable):array
	  {
			 return ['mail', 'database'];
	  }
    /**
     * Get the mail representation of the notification.
     */
	  public function toMail($notifiable):MailMessage
	  {
			 return (new MailMessage)
				  ->subject('New Admin Approval Required')
				  ->line("A new admin registration requires your approval:")
				  ->line("Name: {$this->admin->name}")
				  ->line("Email: {$this->admin->email}")
				  ->action('Review Request', url("/admin/approvals/{$this->admin->id}"))
				  ->line('This request will expire in 1 hours.');
	  }
    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
			return [
				 'admin_id' => $this->admin->id,
				 'email' => $this->admin->email,
				 'requested_at' => now()->toDateTimeString()
			];
    }
}
