<?php
	  
	  namespace App\Models;
	  
	  use App\Casts\DateCast;
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
			 
			 protected $casts
				  = [
						'created_at'        => DateCast::class,
						'updated_at'        => DateCast::class,
				  ];
			 
			 public function profile()
			 {
					return $this->belongsTo(Profile::class);
			 }
	  }