<?php

namespace App\Models;

use App\Casts\DateCast;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
	  protected $fillable = [
			'job_listing_id','profile_id','resume_path'
	  ];
	  
	  protected $casts
			= [
				 'created_at'        => DateCast::class,
				 'updated_at'        => DateCast::class,
			];
	  
	  public function job(): \Illuminate\Database\Eloquent\Relations\BelongsTo
	  {
			 return $this->belongsTo(JobListing::class,'job_listing_id');
	  }
	  
	  public function profile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
	  {
			 return $this->belongsTo(Profile::class);
	  }
	  
	  public function statuses()
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
