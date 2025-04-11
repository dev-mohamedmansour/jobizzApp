<?php
	  
	  namespace App\Models;
	  
	  use Illuminate\Database\Eloquent\Factories\HasFactory;
	  use Illuminate\Database\Eloquent\Model;
	  use Illuminate\Support\Facades\Storage;
	  use Illuminate\Support\Str;
	  
	  class Profile extends Model
	  {
			 use HasFactory;
			 
			 protected $fillable = [
				  'user_id',
				  'title_job',
				  'job_position',
				  'is_default',
				  'profile_image',
			 ];
			 
			 public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
			 {
					return $this->belongsTo(User::class);
			 }
			 // Accessor for image URL
			 public function getProfileImageUrlAttribute()
			 {
					if ($this->profile_image) {
						  return Str::startsWith($this->profile_image, 'http')
								? $this->profile_image
								: Storage::url($this->profile_image);
					}
					return 'https://jobizaa.com/images/nonPhoto.jpg'; // Default placeholder
			 }
			 public function educations(): \Illuminate\Database\Eloquent\Relations\HasMany
			 {
					return $this->hasMany(Education::class);
			 }
			 
			 public function experiences(): \Illuminate\Database\Eloquent\Relations\HasMany
			 {
					return $this->hasMany(Experience::class);
			 }
			 
			 public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
			 {
					return $this->hasMany(Document::class);
			 }
			 
			 public function documentImages(): \Illuminate\Database\Eloquent\Relations\HasMany
			 {
					return $this->hasMany(DocumentImage::class);
			 }
			 
			 public function cvs(): \Illuminate\Database\Eloquent\Relations\HasMany
			 {
					return $this->hasMany(Document::class)->where('type', 'cv');
			 }
			 
			 public function portfolios()
			 {
					return $this->documents()->portfolios();
			 }
			 
			 public function hasMaxPortfolios(): bool
			 {
					return $this->portfolios()->count() >= 2;
			 }
			 
			 public function hasPdfPortfolio(): bool
			 {
					return $this->portfolios()->where('format', 'pdf')->exists();
			 }
	  }