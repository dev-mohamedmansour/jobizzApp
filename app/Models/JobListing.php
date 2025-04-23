<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobListing extends Model
{
	  protected $fillable = [
			'title', 'description',
			'logo',
			'requirement', 'location',
			'job_status',
			'benefits', 'salary',
			'job_type'
	  ];
	  
	  protected $casts = [
			'created_at' => 'date:Y-m-d',
			'updated_at' => 'date:Y-m-d',
	  ];
	  
	  public function category()
	  {
			 return $this->belongsTo(Category::class);
	  }
	  
	  public function applications()
	  {
			 return $this->hasMany(Application::class);
	  }
	  
	  public function company()
	  {
			 return $this->belongsTo(Company::class);
	  }
	  
	  public function scopeActiveJobs($query)
	  {
			 return $query->where('job_status', 'open'); // Adjust the condition based on how you track active jobs
	  }
//	  public function activeJobs(): \Illuminate\Database\Eloquent\Relations\HasMany
//	  {
//			 return $this->hasMany(JobListing::class)->where('job_status', 'open');
//	  }
}
