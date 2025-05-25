<?php
	  
	  namespace App\Http\Resources;
	  
	  use Illuminate\Http\Resources\Json\JsonResource;
	  use Illuminate\Support\Facades\Cache;
	  use Illuminate\Support\Facades\Log;
	  
	  class UserResource extends JsonResource
	  {
			 public function toArray($request): array
			 {
					$cacheKey = "user_resource_{$this->id}_" . now()->format('YmdH');
					try {
						  return Cache::remember($cacheKey, now()->addHours(1), function () {
								 $profile = $this->defaultProfile ?? $this->load('defaultProfile')->defaultProfile;
								 
								 return [
									  'id' => $this->id,
									  'fullName' => $this->name,
									  'email' => $this->email,
									  'profile' => [
											'id' => $profile->id,
											'is_default' => $profile->is_default,
											'title_job' => $profile->title_job,
											'job_position' => $profile->job_position,
											'profile_image' => $profile->profile_image,
									  ],
								 ];
						  });
					} catch (\Exception $e) {
						  Log::error('Redis cache error in UserResource: ' . $e->getMessage());
						  
						  $profile = $this->defaultProfile ?? $this->load('defaultProfile')->defaultProfile;
						  
						  return [
								'id' => $this->id,
								'fullName' => $this->name,
								'email' => $this->email,
								'profile' => [
									 'id' => $profile->id,
									 'is_default' => $profile->is_default,
									 'title_job' => $profile->title_job,
									 'job_position' => $profile->job_position,
									 'profile_image' => $profile->profile_image,
								],
						  ];
					}
			 }
	  }