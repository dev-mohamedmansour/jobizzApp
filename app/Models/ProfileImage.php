<?php
	  
	  namespace App\Models;
	  
	  use Illuminate\Database\Eloquent\Factories\HasFactory;
	  use Illuminate\Database\Eloquent\Model;
	  
	  class ProfileImage extends Model
	  {
			 use HasFactory;
			 
			 protected $fillable = [
				  'profile_id',
				  'path',
				  'is_main'
			 ];
			 
			 public function profile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
			 {
					return $this->belongsTo(Profile::class);
			 }
	  }