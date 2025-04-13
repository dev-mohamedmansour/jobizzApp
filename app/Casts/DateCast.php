<?php
	  
	  namespace App\Casts;
	  
	  use Carbon\Carbon;
	  use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
	  
	  class DateCast implements CastsAttributes
	  {
			 public function get($model, $key, $value, $attributes)
			 {
					return Carbon::parse($value)->toDateString();
			 }
			 
			 public function set($model, $key, $value, $attributes)
			 {
					return $value;
			 }
	  }