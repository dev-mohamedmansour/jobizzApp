<?php

namespace App\Models;

use App\Casts\DateCast;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
	  protected $fillable = [
			'id','name', 'slug',
	  ];
	  
	  protected $casts
			= [
				 'created_at'        => DateCast::class,
				 'updated_at'        => DateCast::class,
			];
	  
	  public function jobs(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
	  {
			 return $this->belongsToMany(JobListing::class);
	  }
}
