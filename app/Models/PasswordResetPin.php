<?php
	  
	  namespace App\Models;
	  
	  use Illuminate\Database\Eloquent\Model;
	  
	  class PasswordResetPin extends Model
	  {
			 public $timestamps = false;
			 protected $fillable = ['email', 'pin',];
	  }
