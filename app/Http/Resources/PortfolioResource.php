<?php
	  
	  namespace App\Http\Resources;
	  
	  use Illuminate\Http\Resources\Json\JsonResource;
	  
	  class PortfolioResource extends JsonResource
	  {
			 public function toArray($request)
			 {
					return [
						 'id' => $this->id,
						 'name' => $this->name,
						 'type' => $this->type,
						 'format' => $this->format,
						 'image_count' => $this->image_count,
						 'images' => $this->images->map(fn($image) => [
							  'id' => $image->id,
							  'path' => $image->path,
							  'mime_type' => $image->mime_type,
						 ]),
					];
			 }
	  }