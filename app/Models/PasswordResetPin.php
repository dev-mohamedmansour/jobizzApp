<?php
	  
	  namespace App\Models;
	  
	  use Illuminate\Database\Eloquent\Model;
	  
	  class PasswordResetPin extends Model
	  {
			 public $timestamps = true;
			 protected $fillable = ['email', 'pin','type'];
	  }
