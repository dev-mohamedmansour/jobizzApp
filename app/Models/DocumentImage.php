<?php

// app/Models/DocumentImage.php
	  namespace App\Models;
	  
	  use Illuminate\Database\Eloquent\Factories\HasFactory;
	  use Illuminate\Database\Eloquent\Model;
	  
	  class DocumentImage extends Model
	  {
			 use HasFactory;
			 
			 protected $fillable = ['document_id', 'path'];
			 
			 public function document(): \Illuminate\Database\Eloquent\Relations\BelongsTo
			 {
					return $this->belongsTo(Document::class);
			 }
			 
			 // Helper method to check image count
			 public static function imageCount($documentId)
			 {
					return self::where('document_id', $documentId)->count();
			 }
	  }
