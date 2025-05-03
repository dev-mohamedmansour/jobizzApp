<?php
	  
	  namespace App\Models;
	  
	  use App\Casts\DateCast;
	  use Illuminate\Database\Eloquent\Factories\HasFactory;
	  use Illuminate\Database\Eloquent\Model;
	  use Illuminate\Support\Facades\Storage;
	  
	  class DocumentImage extends Model
	  {
			 use HasFactory;
			 
			 protected $fillable = [
				  'document_id',
				  'path',
				  'mime_type'
			 ];
			 
			 protected $casts
				  = [
						'created_at'        => 'date:Y-m-d',
						'updated_at'        => 'date:Y-m-d',
				  ];
			 
			 public function document(): \Illuminate\Database\Eloquent\Relations\BelongsTo
			 {
					return $this->belongsTo(Document::class);
			 }
			 
	  }