<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
	  protected $fillable = [
			'id','job_id','profile_id','status','resume_path'
	  ];
	  
	  protected $casts
			= [
				 'created_at'        => 'date:Y-m-d',
				 'updated_at'        => 'date:Y-m-d',
			];
	  
	  public function job(): BelongsTo
	  {
			 return $this->belongsTo(JobListing::class,'job_id');
	  }
	  
	  public function profile(): BelongsTo
	  {
			 return $this->belongsTo(Profile::class);
	  }
	  
	  public function statuses(): HasMany
	  {
			 return $this->hasMany(ApplicationStatusHistory::class)
				  ->orderByDesc('created_at');
	  }
	  
	  public function currentStatus()
	  {
			 return $this->hasOne(ApplicationStatusHistory::class)
				  ->orderByDesc('created_at')
				  ->limit(1);
	  }
}
