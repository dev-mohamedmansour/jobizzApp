<?php
	  
	  namespace App\Models;
	  
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
			 
			 public function document(): \Illuminate\Database\Eloquent\Relations\BelongsTo
			 {
					return $this->belongsTo(Document::class);
			 }
			 
	  }