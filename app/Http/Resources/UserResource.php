<?php
	  
	  namespace App\Http\Resources;
	  
	  use Illuminate\Http\Resources\Json\JsonResource;
	  
	  class UserResource extends JsonResource
	  {
			 private mixed $name;
			 private mixed $email;
			 
			 public function toArray($request): array
			 {
					return [
						 'id' => $this->id,
						 'fullName' => $this->name,
						 'email' => $this->email,
						 'profile' => $this->whenLoaded('defaultProfile'),
					];
			 }
	  }