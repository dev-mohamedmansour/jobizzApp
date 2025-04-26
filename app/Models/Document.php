<?php

// app/Models/Document.php
	  namespace App\Models;
	  
	  use App\Casts\DateCast;
	  use App\DocumentType;
	  use Illuminate\Database\Eloquent\Factories\HasFactory;
	  use Illuminate\Database\Eloquent\Model;
	  
	  class Document extends Model
	  {
			 use HasFactory;
			 
			 protected $fillable
				  = [
						'id',
						'profile_id',
						'name',
						'type',
						'format',
						'path',
						'url',
						'image_count',
						'updated_at'
				  ];
			 
			 protected $casts
				  = [
						'type' => DocumentType::class,
						'created_at'        => DateCast::class,
						'updated_at'        => 'datetime',
				  ];
			 
			 public static function cvCount($profileId)
			 {
					return self::where('profile_id', $profileId)
						 ->where('type', 'cv')
						 ->count();
			 }
			 
			 public function profile(
			 ): \Illuminate\Database\Eloquent\Relations\BelongsTo
			 {
					return $this->belongsTo(Profile::class);
			 }
			 
			 public function images(
			 ): \Illuminate\Database\Eloquent\Relations\HasMany
			 {
					return $this->hasMany(DocumentImage::class);
			 }
			 
			 // Helper method to check CV count
			 
			 public function isPortfolio(): bool
			 {
					return $this->type === 'portfolio';
			 }
			 
			 public function incrementImageCount($count = 1)
			 {
					$this->update(['image_count' => $this->image_count + $count]);
			 }
			 
			 public function decrementImageCount($count = 1)
			 {
					$newCount = max(0, $this->image_count - $count);
					$this->update(['image_count' => $newCount]);
			 }
			 
	  }