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
				  'format',
				  'path',
				  'url',
				  'max_images'
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
			 public function scopePortfolios($query)
			 {
					return $query->where('type', 'portfolio');
			 }
			 
			 // Helper methods
			 public function isPdfPortfolio(): bool
			 {
					return $this->type === 'portfolio' && $this->format === 'pdf';
			 }
			 
			 public function isImagePortfolio(): bool
			 {
					return $this->type === 'portfolio' && $this->format === 'images';
			 }
			 
			 public function isUrlPortfolio(): bool
			 {
					return $this->type === 'portfolio' && $this->format === 'url';
			 }
			 
			 public function hasReachedImageLimit(): bool
			 {
					return $this->isImagePortfolio() && $this->images()->count() >= $this->max_images;
			 }
	  }