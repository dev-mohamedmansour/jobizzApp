<?php
	  
	  namespace App\Models;
	  
	  use Illuminate\Database\Eloquent\Factories\HasFactory;
	  use Illuminate\Database\Eloquent\Model;
	  
	  class Profile extends Model
	  {
			 use HasFactory;
			 
			 protected $fillable = [
				  'user_id',
				  'title',
				  'job_position',
				  'is_default'
			 ];
			 
			 public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
			 {
					return $this->belongsTo(User::class);
			 }
			 
			 public function images(): \Illuminate\Database\Eloquent\Relations\HasMany
			 {
					return $this->hasMany(ProfileImage::class);
			 }
			 
			 public function mainImage(): \Illuminate\Database\Eloquent\Relations\HasOne
			 {
					return $this->hasOne(ProfileImage::class)->where('is_main', true);
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
					return $this->hasMany(ProfileDocument::class);
			 }
			 
			 public function cvs(): \Illuminate\Database\Eloquent\Relations\HasMany
			 {
					return $this->hasMany(ProfileDocument::class)->where('type', 'cv');
			 }
			 
			 public function portfolios(): \Illuminate\Database\Eloquent\Relations\HasMany
			 {
					return $this->hasMany(ProfileDocument::class)->where('type', 'portfolio');
			 }
	  }