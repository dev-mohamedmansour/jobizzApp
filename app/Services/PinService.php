<?php
	  
	  namespace App\Services;
	  
	  use App\Mail\PinNotificationMail;
	  use App\Models\PasswordResetPin;
	  use App\Models\User;
	  use Illuminate\Support\Facades\Mail;
	  
	  class PinService
	  {
			 /**
			  * Generate and send a PIN for the given user and type
			  *
			  * @param User   $user
			  * @param string $type 'verification' or 'reset'
			  *
			  * @return string The generated PIN
			  */
			 public function generateAndSendPin(User $user, string $type): string
			 {
					$pin = $this->generatePin();
					$expiry = $this->getExpiryMinutes($type);
					
					$this->storePin($user->email, $pin, $type);
					
					$this->sendPinEmail($user->email, $pin, $type, $expiry);
					
					return $pin;
			 }
			 
			 /**
			  * Generate a 4-digit PIN
			  */
			 protected function generatePin(): string
			 {
					return str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
			 }
			 
			 /**
			  * Get expiry time in minutes based on type
			  */
			 protected function getExpiryMinutes(string $type): int
			 {
					return $type === 'verification'
						 ? config('auth.verification.expire', 1440)  // 24 hours
						 : config('auth.passwords.users.expire', 60); // 1 hour
			 }
			 
			 /**
			  * Store the PIN in the appropriate storage
			  */
			 protected function storePin(string $email, string $pin, string $type
			 ): void {
					if ($type === 'verification') {
						  User::where('email', $email)->update([
								'pin_code'       => $pin,
								'pin_created_at' => now()
						  ]);
					} else {
						  PasswordResetPin::updateOrCreate(
								['email' => $email],
								['pin' => $pin, 'created_at' => now()]
						  );
					}
			 }
			 
			 /**
			  * Send the PIN notification email
			  */
			 protected function sendPinEmail(string $email, string $pin,
				  string $type, int $expiry
			 ): void {
					Mail::to($email)->send(
						 new PinNotificationMail($pin, $type, $expiry)
					);
			 }
			 
			 /**
			  * Verify a PIN for the given user and type
			  */
			 public function verifyPin(User $user, string $pin, string $type): bool
			 {
					if ($type === 'verification') {
						  return $this->verifyEmailPin($user, $pin);
					}
					
					return $this->verifyResetPin($user->email, $pin);
			 }
			 
			 protected function verifyEmailPin(User $user, string $pin): bool
			 {
					if (!$user->pin_code || $user->pin_code !== $pin) {
						  return false;
					}
					
					if ($user->pin_created_at->addMinutes(
						 config('auth.verification.expire', 1440)
					)->isPast()
					) {
						  return false;
					}
					
					$user->pin_code = null;
					$user->pin_created_at = null;
					$user->confirmed_email = true;
					$user->email_verified_at=now();
					$user->save();
					
					return true;
			 }
			 
			 protected function verifyResetPin(string $email, string $pin): bool
			 {
					$record = PasswordResetPin::where('email', $email)
						 ->where('pin', $pin)
						 ->first();
					
					if (!$record || !$record->isValid()) {
						  return false;
					}
					
					return true;
			 }
	  }