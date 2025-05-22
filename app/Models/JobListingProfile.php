<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
	  protected $table = 'job_listing_profile';
	  protected $fillable = ['id','profile_id', 'job_listing_id'];
	  public $timestamps = true;
	  
	  /**
		* Get the profile that marked this favorite.
		*/
//	  public function profile(): BelongsTo
//	  {
//			 return $this->belongsTo(Profile::class);
//	  }
//
//	  /**
//		* Get the job that was marked as favorite.
//		*/
//	  public function job(): BelongsTo
//	  {
//			 return $this->belongsTo(JobListing::class);
//	  }
}
