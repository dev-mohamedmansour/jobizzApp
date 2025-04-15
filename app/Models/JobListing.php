<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobListing extends Model
{
	  protected $fillable = [
			'title', 'description','requirement', 'location','job_status',
			'salary_range', 'employment_type', 'expiry_date'
	  ];
	  
	  public function categories()
	  {
			 return $this->belongsToMany(Category::class);
	  }
	  
	  public function applications()
	  {
			 return $this->hasMany(Application::class);
	  }
	  
	  public function company()
	  {
			 return $this->belongsTo(Company::class);
	  }
	  public function activeJobs(): \Illuminate\Database\Eloquent\Relations\HasMany
	  {
			 return $this->hasMany(JobListing::class)->where('job_status', 'open');
	  }
}
