<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobListing extends Model
{
	  protected $fillable = [
			'title', 'description', 'location',
			'salary_range', 'employment_type', 'expiry_date'
	  ];
	  
	  public function company()
	  {
			 return $this->belongsTo(Company::class);
	  }
}
