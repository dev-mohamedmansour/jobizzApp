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
				  'caption',
				  'mime_type',
				  'order',
				  'is_cover',
				  'url' // Added for direct URL access
			 ];
			 
			 protected $casts = [
				  'is_cover' => 'boolean',
				  'order' => 'integer'
			 ];
			 
			 public function document(): \Illuminate\Database\Eloquent\Relations\BelongsTo
			 {
					return $this->belongsTo(Document::class);
			 }
			 
			 // Get full public URL for the image
			 public function getImageUrlAttribute()
			 {
					return $this->url ?? Storage::disk('public')->url($this->path);
			 }
			 
			 // Scope for cover image
			 public function scopeCover($query)
			 {
					return $query->where('is_cover', true);
			 }
			 
			 // Scope for ordered images
			 public function scopeOrdered($query, $direction = 'asc')
			 {
					return $query->orderBy('order', $direction);
			 }
			 
			 // Check if image exists in storage
			 public function existsInStorage()
			 {
					return Storage::disk('public')->exists($this->path);
			 }
			 
			 // Helper method to count images for a document
			 public static function countForDocument($documentId): int
			 {
					return self::where('document_id', $documentId)->count();
			 }
			 
			 // Helper to get cover image for a document
			 public static function getCoverForDocument($documentId)
			 {
					return self::where('document_id', $documentId)
						 ->cover()
						 ->first();
			 }
	  }