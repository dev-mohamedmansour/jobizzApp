<?php
	  
	  namespace App\Models;
	  
	  use Illuminate\Database\Eloquent\Model;
	  
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
			 
			 public function admin()
			 {
					return $this->belongsTo(Admin::class);
			 }
			 
			 public function admins()
			 {
					return $this->hasMany(Admin::class);
			 }
			 
			 public function deleteNonAdminsAndNonSuperAdmins(): void
			 {
					$this->admins()->whereNotIn('role', ['admin', 'super admin'])->delete();
			 }
			 
			 public function jobs()
			 {
					return $this->hasMany(JobListing::class);
			 }
			 
//			 public function jobListings()
//			 {
//					return $this->hasMany(JobListing::class);
//			 }
	  }
