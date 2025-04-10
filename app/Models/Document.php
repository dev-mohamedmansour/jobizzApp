<?php

// app/Models/Document.php
	  namespace App\Models;
	  
	  use App\DocumentType;
	  use Illuminate\Database\Eloquent\Factories\HasFactory;
	  use Illuminate\Database\Eloquent\Model;
	  
	  class Document extends Model
	  {
			 use HasFactory;
			 
			 protected $fillable = [
				  'profile_id',
				  'name',
				  'type',
				  'path',
				  'url'
			 ];
			 
			 protected $casts = [
				  'type' => DocumentType::class,
			 ];
			 
			 public function profile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
			 {
					return $this->belongsTo(Profile::class);
			 }
			 
			 public function images(): \Illuminate\Database\Eloquent\Relations\HasMany
			 {
					return $this->hasMany(DocumentImage::class);
			 }
			 
			 // Helper method to check CV count
			 public static function cvCount($profileId)
			 {
					return self::where('profile_id', $profileId)
						 ->where('type', 'cv')
						 ->count();
			 }
	  }