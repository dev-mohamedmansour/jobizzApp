<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
	  protected $fillable = ['name', 'description', 'industry', 'website', 'logo_path', 'admin_id'];
	  
	  public function admin()
	  {
			 return $this->belongsTo(Admin::class);
	  }
	  
	  public function jobListings()
	  {
			 return $this->hasMany(JobListing::class);
	  }
}
