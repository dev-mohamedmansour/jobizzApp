<?php
	  
	  namespace App\Models;
	  
	  use App\Casts\DateCast;
	  use Illuminate\Contracts\Auth\MustVerifyEmail;
	  use Illuminate\Database\Eloquent\Factories\HasFactory;
	  use Illuminate\Foundation\Auth\User as Authenticatable;
	  use Illuminate\Notifications\Notifiable;
	  use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
	  
	  class Admin extends Authenticatable implements JWTSubject ,MustVerifyEmail
	  {
			 use HasFactory, Notifiable;
			 
			 protected $fillable = [
				  'name', 'email', 'password',
				  'pin_code', 'pin_created_at',
				  'confirmed_email', 'email_verified_at'
			 ];
			 
			 protected $casts = [
				  'confirmed_email' => 'boolean',
				  'pin_created_at' => 'datetime',
				  'email_verified_at' => 'datetime'
			 ];
			 protected $hidden = [
				  'password',
				  'pin_code'
			 ];
			 
			 protected $dates = [
				  'email_verified_at' => DateCast::class,
				  'created_at' => DateCast::class,
				  'updated_at' => DateCast::class,
				  'pin_created_at'=> DateCast::class,
			 ];
			 
			 protected function serializeDate(\DateTimeInterface $date)
			 {
					return $date->toDateString(); // Format: '2025-04-13'
			 }
			 
			 
			 public function getJWTIdentifier() {
					return $this->getKey();
			 }
			 
			 public function getJWTCustomClaims(): array
			 {
					return [
						 'role' => 'admin',
						 'email' => $this->email
					];
			 }
	  }