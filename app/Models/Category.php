<?php

namespace App\Models;

use App\Casts\DateCast;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
	  protected $fillable = [
			'id','name', 'slug','image',
	  ];
	  
	  protected $casts
			= [
				 'created_at'        => 'date:Y-m-d',
				 'updated_at'        => 'date:Y-m-d',
			];
	  
	  public function jobs(): \Illuminate\Database\Eloquent\Relations\HasMany
	  {
			 return $this->hasMany(JobListing::class, 'category_name', 'name');
	  }
}
