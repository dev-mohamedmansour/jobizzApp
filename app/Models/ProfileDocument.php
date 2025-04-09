<?php
	  
	  namespace App\Models;
	  
	  use Illuminate\Database\Eloquent\Factories\HasFactory;
	  use Illuminate\Database\Eloquent\Model;
	  
	  class ProfileDocument extends Model
	  {
			 use HasFactory;
			 
			 protected $fillable = [
				  'profile_id',
				  'name',
				  'path',
				  'type',
				  'url'
			 ];
			 
			 public function profile()
			 {
					return $this->belongsTo(Profile::class);
			 }
	  }