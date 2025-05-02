<?php
	  
	  namespace App\Services;
	  
	  use App\Mail\PinNotificationMail;
	  use App\Models\Admin;
	  use App\Models\PasswordResetPin;
	  use App\Models\User;
	  use Carbon\Carbon;
	  use Exception;
	  use Illuminate\Support\Facades\Log;
	  use Illuminate\Support\Facades\Mail;
	  use Random\RandomException;
	  
	  class PinService
	  {
			 /**
			  * Generate and send a PIN for the given model (User/Admin)
			  *
			  * @throws RandomException
			  */
			 protected function generatePin(): string
			 {
					return str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT);
			 }
			 protected function getExpiryMinutes(string $type): int
			 {
					return $type === 'verification'
						 ? (int)config('auth.verification.expire', 15)  //  15 minutes
						 : (int)config('auth.passwords.users.expire', 15); // 15 minutes
			 }
			 
			 protected function sendPinEmail(string $email, string $pin, string $type, int $expiry,string $name): bool
			 {
					try {
						  Mail::to($email)->send(new PinNotificationMail($pin, $type, $expiry,$name));
						  return true;
					} catch (Exception $e) {
						  Log::error("Failed to send email to {$email}: " . $e->getMessage());
						  return false;
					}
			 }
			 public function generateAndSendPin($verifiable, string $type): array
			 {
					$pin = $this->generatePin();
					$expiry = $this->getExpiryMinutes($type);
					
					$this->storePin($verifiable, $pin, $type);
					$emailSent = $this->sendPinEmail(
						 $verifiable->email, $pin, $type, $expiry,$verifiable->name
					);
					
					return ['pin' => $pin, 'email_sent' => $emailSent];
			 }
			 
			 /**
			  * Store PIN based on type and model
			  */
			 protected function storePin($verifiable, string $pin, string $type
			 ): void {
					if ($type === 'verification') {
						  $verifiable->update([
								'pin_code'       => $pin,
								'pin_created_at' => now()
						  ]);
					} else {
						  $modelType = $verifiable instanceof User ? 'user' : 'admin';
						  
						  PasswordResetPin::updateOrCreate(
								['email' => $verifiable->email],
								[
									 'pin'        => $pin,
									 'type'       => $modelType,
									 'created_at' => now()
								]
						  );
					}
			 }
			 
			 /**
			  * Verify PIN for either User or Admin
			  */
			 public function verifyPin($verifiable, string $pin, string $type): bool
			 {
					if ($type === 'verification') {
						  return $this->verifyEmailPin($verifiable, $pin);
					}
					
					// For password reset verification
					return $this->verifyResetPin($verifiable, $pin);
			 }
			 
			 protected function verifyResetPin($verifiable, string $pin): bool
			 {
					$modelType = $verifiable instanceof User ? 'user' : 'admin';
					
					$record = PasswordResetPin::where('email', $verifiable->email)
						 ->where('pin', $pin)
						 ->where('type', $modelType)
						 ->first();
					
					if (!$record || $this->isPinExpired($record->created_at)) {
						  return false;
					}
					
					$record->delete();
					return true;
			 }
			 
			 protected function isPinExpired($createdAt): bool
			 {
					$expiryMinutes = (int) config('auth.passwords.users.expire', 15);// 15 minutes
					return now()->diffInMinutes($createdAt) > $expiryMinutes;
			 }
			 
			 protected function verifyEmailPin($verifiable, string $pin): bool
			 {
					// Log the entered PIN and stored PIN for debugging
					Log::info("Entered PIN: " . $pin);
					Log::info("Stored PIN: " . $verifiable->pin_code);
					
					if (!$verifiable->pin_code || $verifiable->pin_code !== $pin) {
						  Log::info("PIN does not match.");
						  return false;
					}
					
					$expiry = 10; // 10 minutes expiration
					
					$pinCreatedAt = $verifiable->pin_created_at;
					Log::info("PIN Created At: " . $pinCreatedAt);
					
					if (!$pinCreatedAt instanceof Carbon) {
						  Log::error("PIN created_at is not a valid Carbon instance.");
						  return false;
					}
					
					$expiryTime = $pinCreatedAt->copy()->addMinutes($expiry);
					Log::info("PIN Expiry Time: " . $expiryTime);
					
					if (now()->gt($expiryTime)) {
						  Log::info("PIN has expired.");
						  return false;
					}
					
					// If the PIN is valid and not expired, update the user's status
					$verifiable->update([
						 'pin_code'          => null,
						 'pin_created_at'    => null,
						 'confirmed_email'   => true,
						 'email_verified_at' => now(),
						 'updated_at'        => now()
					]);
					
					Log::info("PIN verified successfully.");
					return true;
			 }
			 
	  }


