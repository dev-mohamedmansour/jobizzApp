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
			 
			 public function admin()
			 {
					return $this->belongsTo(Admin::class);
			 }
			 
			 public function admins()
			 {
					return $this->hasMany(Admin::class);
			 }
			 
			 public function jobs()
			 {
					return $this->hasMany(JobListing::class);
			 }
			 
			 public function jobListings()
			 {
					return $this->hasMany(JobListing::class);
			 }
	  }
