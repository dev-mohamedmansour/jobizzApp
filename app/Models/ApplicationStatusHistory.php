<?php
	  
	  namespace App\Models;
	  
	  use Illuminate\Database\Eloquent\Model;
	  
	  class ApplicationStatusHistory extends Model
	  {
			 protected $fillable = ['status', 'feedback'];
	  }