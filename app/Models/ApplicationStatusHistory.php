<?php
	  
	  namespace App\Models;
	  
	  use App\Casts\DateCast;
	  use Illuminate\Database\Eloquent\Model;
	  
	  class ApplicationStatusHistory extends Model
	  {
			 protected $fillable = ['status', 'feedback'];
			 
			 protected $casts
				  = [
						'created_at'        => 'date:Y-m-d',
						'updated_at'        => 'date:Y-m-d',
				  ];
	  }