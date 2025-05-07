<?php
	  
	  namespace App\Models;
	  
	  use App\Casts\DateCast;
	  use Carbon\Carbon;
	  use Illuminate\Database\Eloquent\Model;
	  
	  class ApplicationStatusHistory extends Model
	  {
			 protected $fillable = ['id','application_id','status', 'feedback'];
			 
			 protected $casts = [
				  'updated_at' => 'datetime',
			 ];
			 
			 public function application(): \Illuminate\Database\Eloquent\Relations\BelongsTo
			 {
					return $this->belongsTo(Application::class);
			 }
	  }