<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class JobListing extends Model
{
	  protected $table = 'job_listings';
	  
	  protected $fillable = [
			'title', 'description',
			'requirement', 'location',
			'job_status',
			'benefits', 'salary',
			'job_type','category_name','company_id','position'
	  ];
	  
	  protected $casts = [
			'created_at' => 'date:Y-m-d',
			'updated_at' => 'date:Y-m-d',
	  ];
	  
	  public function category(): BelongsTo
	  {
			 return $this->belongsTo(Category::class, 'category_name', 'name');
	  }
	  
	  public function applications(): HasMany
	  {
			 return $this->hasMany(Application::class, 'job_id');
	  }
	  
	  public function company(): BelongsTo
	  {
			 return $this->belongsTo(Company::class,'company_id');
	  }
	  public function getCompanyLogoAttribute()
	  {
			 return $this->company ? $this->company->logo : null;
	  }
	  
	  public function scopeActiveJobs($query)
	  {
			 return $query->where('job_status', 'open');
	  }
	  
	  public function favoritesJob(): BelongsToMany
	  {
			 return $this->belongsToMany(Profile::class);
	  }
	  
}
