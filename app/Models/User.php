<?php
	  
	  namespace App\Models;
	  
	  use App\Casts\DateCast;
	  use Illuminate\Contracts\Auth\MustVerifyEmail;
	  use Illuminate\Database\Eloquent\Factories\HasFactory;
	  use Illuminate\Database\Eloquent\Relations\HasMany;
	  use Illuminate\Database\Eloquent\Relations\HasOne;
	  use Illuminate\Foundation\Auth\User as Authenticatable;
	  use Illuminate\Notifications\Notifiable;
	  use Illuminate\Support\Facades\Storage;
	  use Illuminate\Support\Str;
	  use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
	  
	  class User extends Authenticatable implements JWTSubject, MustVerifyEmail
	  {
			 use HasFactory, Notifiable;
			 public function getJWTIdentifier()
			 {
					return $this->getKey();
			 }
			 
			 public function getJWTCustomClaims(): array
			 {
					return [];
			 }
			 
			 /**
			  * The attributes that are mass assignable.
			  *
			  * @var array<int, string>
			  */
			 protected $fillable
				  = [
						'id',
						'name',
						'email',
						'password',
						'provider_id',
						'provider_name',
						'confirmed_email',
						'pin_code',
						'pin_created_at',
						'email_verified_at',
						'profile_image',
				  ];
			 
			 /**
			  * The attributes that should be hidden for serialization.
			  *
			  * @var array<int, string>
			  */
			 
			 protected $casts = [
				  'pin_created_at' => 'date:Y-m-d',
				  'created_at'        => 'date:Y-m-d',
				  'updated_at'        => 'date:Y-m-d',
			 ];
			 protected $hidden
				  = [
						'password',
						'remember_token',
						'pin_code'
				  ];
			 
			 /**
			  * Get the attributes that should be cast.
			  *
			  * @return array<string, string>
			  */
			 protected function casts(): array
			 {
					return [
						 'confirmed_email'   => 'boolean',
						 'email_verified_at' => 'date:Y-m-d',
						 'password'          => 'hashed'
					];
			 }
			 
			 private string $pin_code;
			 /**
			  * @var true
			  */
			 private bool $confirmed_email;
			 
			 public function profiles(): HasMany
			 {
					return $this->hasMany(Profile::class);
			 }
			 
			 public function defaultProfile(): HasOne
			 {
					return $this->hasOne(Profile::class)->where('is_default', '=',1);
			 }
			 
			 // Generate PIN for email verification
			 public function generateVerificationPin(): string
			 {
					$this->pin_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
					$this->save();
					return $this->pin_code;
			 }
			 
			 // Verify PIN code
			 public function verifyPin($pin): bool
			 {
					if ($this->pin_code == $pin) {
						  $this->confirmed_email = true;
						  $this->pin_code = null;
						  $this->save();
						  return true;
					}
					return false;
			 }
			 
	  }
