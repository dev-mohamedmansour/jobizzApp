<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobListingProfile extends Model
{
	  protected $fillable = ['profile_id', 'job_listing_id'];
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
