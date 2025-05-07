<?php
	  
	  namespace App\Http\Resources;
	  
	  use Illuminate\Http\Resources\Json\JsonResource;
	  
	  class StatusResource extends JsonResource
	  {
			 public function toArray($request)
			 {
					return [
						 'id' => $this->id,
						 'application_id' => $this->application_id,
						 'status' => $this->status,
						 'updated_at' => $this->updated_at->format('Y-m-d H:i'),
					];
			 }
	  }