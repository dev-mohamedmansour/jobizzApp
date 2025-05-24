<?php
	  
	  namespace App\Http\Resources;
	  
	  use Illuminate\Http\Resources\Json\JsonResource;
	  
	  class JobListingResource extends JsonResource
	  {
			 public function toArray($request): array
			 {
					return [
						 'id' => $this->id,
						 'title' => $this->title,
						 'company_id' => $this->company_id,
						 'location' => $this->location,
						 'job_type' => $this->job_type,
						 'salary' => $this->salary,
						 'position' => $this->position,
						 'category_name' => $this->category_name,
						 'description' => $this->description,
						 'requirement' => $this->requirement,
						 'benefits' => $this->benefits,
						 'companyName' => $this->company->name,
						 'companyLogo' => $this->company->logo,
						 'isFavorite' => $this->isFavoritedByProfile($this->pivot->profile_id ?? null),
					];
			 }
	  }