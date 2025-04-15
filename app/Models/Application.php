<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
	  
	  public function job(): \Illuminate\Database\Eloquent\Relations\BelongsTo
	  {
			 return $this->belongsTo(JobListing::class);
	  }
	  
	  public function profile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
	  {
			 return $this->belongsTo(Profile::class);
	  }
}
