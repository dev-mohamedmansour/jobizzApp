<?php
	  
	  namespace App\Models;
	  
	  use App\Casts\DateCast;
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
			 
			 protected $casts
				  = [
						'created_at'        => 'date:Y-m-d',
						'updated_at'        => 'date:Y-m-d',
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
					return 'https://jobizaa.com/still_images/userDefault.jpg'; // Default placeholder
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
					return $this->documents()->where('type', 'portfolio');
			 }
			 
			 public function isPortfolio(): bool
			 {
					return $this->type === 'portfolio';
			 }
			 
			 public function favorites(): \Illuminate\Database\Eloquent\Relations\HasMany
			 {
					return $this->hasMany(Favorite::class);
			 }
			 
			 public function favoriteJobs()
			 {
					return $this->belongsToMany(JobListing::class, 'favorites', 'profile_id', 'job_id')->withTimestamps();
			 }
	  }