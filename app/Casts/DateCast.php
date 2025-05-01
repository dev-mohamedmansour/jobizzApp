<?php
	  
	  namespace App\Casts;
	  
	  use Carbon\Carbon;
	  use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
	  
	  class DateCast implements CastsAttributes
	  {
			 public function get($model, $key, $value, $attributes)
			 {
					// Parse the date string into a Carbon instance
					return $value ? Carbon::parse($value) : null;
			 }
			 
			 public function set($model, $key, $value, $attributes)
			 {
					// Ensure that the date is stored in the database as a string in the correct format
					return $value instanceof Carbon ? $value->toDateTimeString() : $value;
			 }
	  
	  }