<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
	  protected $fillable = [
			'name', 'slug',
	  ];
	  
	  public function jobs(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
	  {
			 return $this->belongsToMany(JobListing::class);
	  }
}
