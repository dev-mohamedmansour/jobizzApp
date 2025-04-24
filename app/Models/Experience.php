<?php
	  
	  namespace App\Models;
	  
	  use App\Casts\DateCast;
	  use Illuminate\Database\Eloquent\Factories\HasFactory;
	  use Illuminate\Database\Eloquent\Model;
	  
	  class Experience extends Model
	  {
			 use HasFactory;
			 
			 protected $fillable = [
				  'profile_id',
				  'company',
				  'position',
				  'start_date',
				  'end_date',
				  'is_current',
				  'description'
			 ];
			 
			 protected $casts
				  = [
						'created_at'        => DateCast::class,
						'updated_at'        => DateCast::class,
				  ];
			 
			 public function profile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
			 {
					return $this->belongsTo(Profile::class);
			 }
	  }