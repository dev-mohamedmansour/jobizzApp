<?php
	  
	  namespace App\Models;
	  
	  use App\Casts\DateCast;
	  use Illuminate\Database\Eloquent\Factories\HasFactory;
	  use Illuminate\Database\Eloquent\Model;
	  
	  class Education extends Model
	  {
			 use HasFactory;
			 // Explicitly define the table name
			 protected $table = 'educations';
			 protected $fillable = [
				  'profile_id',
				  'college',
				  'degree',
				  'field_of_study',
				  'start_date',
				  'end_date',
				  'is_current',
				  'description',
				  'location',
				  'image',
			 ];
			 
			 protected $casts
				  = [
						'created_at'        => 'date:Y-m-d',
						'updated_at'        => 'date:Y-m-d',
				  ];
			 
			 public function profile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
			 {
					return $this->belongsTo(Profile::class);
			 }
	  }