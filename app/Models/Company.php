<?php
	  
	  namespace App\Models;
	  
	  use Illuminate\Database\Eloquent\Model;
	  use Illuminate\Database\Eloquent\Relations\BelongsTo;
	  use Illuminate\Database\Eloquent\Relations\HasMany;
	  
	  class Company extends Model
	  {
			 protected $fillable
				  = [
						'name',
						'admin_id',
						'logo',
						'description',
						'location',
						'website',
						'size',
						'open_jobs',
						'hired_people',
				  ];
			 /**
			  * The attributes that should be cast to native types.
			  *
			  * @var array
			  */
			 protected $casts = [
				  'created_at' => 'date:Y-m-d',
				  'updated_at' => 'date:Y-m-d',
			 ];
			 
			 public function admin(): BelongsTo
			 {
					return $this->belongsTo(Admin::class);
			 }
			 
			 public function admins(): HasMany
			 {
					return $this->hasMany(Admin::class);
			 }
			 
			 public function deleteNonAdminsAndNonSuperAdmins(): void
			 {
					$this->admins()->whereNotIn('role', ['admin', 'super admin'])->delete();
			 }
			 
			 public function jobs(): HasMany
			 {
					return $this->hasMany(JobListing::class);
			 }
			 
	  }
