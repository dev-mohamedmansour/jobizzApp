<?php
	  
	  namespace App\Models;
	  
	  use App\Casts\DateCast;
	  use Illuminate\Database\Eloquent\Model;
	  
	  class PasswordResetPin extends Model
	  {
			 public $timestamps = true;
			 protected $fillable = ['email', 'pin','type'];
			 
			 protected $casts
				  = [
						'created_at'        => 'date:Y-m-d',
						'updated_at'        => 'date:Y-m-d',
				  ];
	  
	  }

	  