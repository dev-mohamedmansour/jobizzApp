<?php
	  
	  namespace App\Models;
	  
	  use App\Casts\DateCast;
	  use Illuminate\Contracts\Auth\MustVerifyEmail;
	  use Illuminate\Database\Eloquent\Factories\HasFactory;
	  use Illuminate\Foundation\Auth\User as Authenticatable;
	  use Illuminate\Notifications\Notifiable;
	  use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
	  use Spatie\Permission\Traits\HasRoles;
	  
	  /**
		* @method static create(array $array)
		*/
	  class Admin extends Authenticatable implements JWTSubject, MustVerifyEmail
	  {
			 use HasFactory, Notifiable, HasRoles;
			 
			 
			 protected $fillable
				  = [
						'name', 'email', 'password',
						'phone', 'is_approved', 'photo',
						'pin_code', 'pin_created_at',
						'confirmed_email', 'email_verified_at',
						'approved_by', 'company_id'
				  ];
			 protected $guarded = ['is_approved', 'approved_by'];
			 
			 protected $casts
				  = [
						'confirmed_email'   => 'boolean',
						'pin_created_at'    => 'datetime',
						'email_verified_at' => 'date:Y-m-d',
						'created_at'        => 'date:Y-m-d',
						'updated_at'        => 'date:Y-m-d',
				  ];
			 protected $hidden
				  = [
						'password',
						'pin_code'
				  ];
			 
			 protected $dates
				  = [
						'email_verified_at' => 'date:Y-m-d',
						'pin_created_at'    => 'date:Y-m-d',
				  ];
			 protected $guard_name = 'admin';
			 
			 public function scopePending($query)
			 {
					return $query->where('is_approved', false);
			 }
			 
			 public function approver(
			 ): \Illuminate\Database\Eloquent\Relations\BelongsTo
			 {
					return $this->belongsTo(Admin::class, 'approved_by');
			 }
			 
			 public function company(
			 ): \Illuminate\Database\Eloquent\Relations\BelongsTo
			 {
					return $this->belongsTo(Company::class);
			 }
			 
			 public function getJWTIdentifier()
			 {
					return $this->getKey();
			 }
			 
			 public function getJWTCustomClaims(): array
			 {
					return [
						 'roles'       => $this->getRoleNames(),
						 'permissions' => $this->getAllPermissions()->pluck('name'),
						 'company_id'  => $this->company_id
					];
			 }
			 
			 protected function serializeDate(\DateTimeInterface $date)
			 {
					return $date->toDateString(); // Format: '2025-04-13'
			 }
	  }