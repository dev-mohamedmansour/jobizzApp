<?php
	  
	  namespace App\Http\Resources;
	  
	  use Illuminate\Http\Resources\Json\JsonResource;
	  
	  class ProfileResource extends JsonResource
	  {
			 public function toArray($request)
			 {
					$messages = [];
					if ($this->educations->isEmpty()) {
						  $messages[] = 'No education details added yet';
					}
					if ($this->experiences->isEmpty()) {
						  $messages[] = 'No experience details added yet';
					}
					if ($this->cvs->isEmpty()) {
						  $messages[] = 'No CVs uploaded yet';
					}
					if ($this->portfolios->isEmpty()) {
						  $messages[] = 'No portfolios uploaded yet';
					}
					
					$appliedApplications = $this->applications->where('status', 'submitted')->count();
					$interviewApplications = $this->applications->where('status', 'technical-interview')->count();
					$reviewedApplications = $this->applications->where('status', 'reviewed')->count();
					
					return [
						 'id' => $this->id,
						 'user_id' => $this->user_id,
						 'title_job' => $this->title_job,
						 'job_position' => $this->job_position,
						 'is_default' => (bool) $this->is_default,
						 'profile_image' => $this->profile_image,
						 'created_at' => $this->created_at?->format('Y-m-d'),
						 'updated_at' => $this->updated_at?->format('Y-m-d'),
						 'applied_applications' => $appliedApplications,
						 'interview_applications' => $interviewApplications,
						 'reviewed_applications' => $reviewedApplications,
						 'educations' => $this->educations->map(fn($edu) => [
							  'id' => $edu->id,
							  'college' => $edu->college,
							  'degree' => $edu->degree,
							  'field_of_study' => $edu->field_of_study,
							  'start_date' => $edu->start_date,
							  'end_date' => $edu->end_date,
							  'is_current' => (bool) $edu->is_current,
							  'description' => $edu->description,
							  'location' => $edu->location,
							  'image_url' => $edu->image ? Storage::disk('public')->url($edu->image) : null,
						 ])->toArray(),
						 'experiences' => $this->experiences->map(fn($exp) => [
							  'id' => $exp->id,
							  'company' => $exp->company,
							  'position' => $exp->position,
							  'start_date' => $exp->start_date,
							  'end_date' => $exp->end_date,
							  'is_current' => (bool) $exp->is_current,
							  'description' => $exp->description,
							  'location' => $exp->location,
							  'image_url' => $exp->image ? Storage::disk('public')->url($exp->image) : null,
						 ])->toArray(),
						 'cvs' => $this->cvs->map(fn($cv) => [
							  'id' => $cv->id,
							  'name' => $cv->name,
							  'type' => $cv->type,
							  'path' => $cv->path,
						 ])->toArray(),
						 'portfolios' => $this->portfolios->map(fn($portfolio) => [
							  'id' => $portfolio->id,
							  'name' => $portfolio->name,
							  'type' => $portfolio->type,
							  'format' => $portfolio->format,
							  'image_count' => $portfolio->image_count,
							  'path' => $portfolio->path,
							  'url' => $portfolio->url,
							  'images' => $portfolio->images?->map(fn($image) => [
										 'id' => $image->id,
										 'path' => $image->path,
										 'mime_type' => $image->mime_type,
									])->toArray() ?? [],
						 ])->toArray(),
						 'documents' => $this->documents?->map(fn($doc) => [
									'id' => $doc->id,
									'name' => $doc->name,
									'type' => $doc->type,
									'format' => $doc->format,
									'url' => $doc->url ?? $doc->path,
							  ])->toArray() ?? [],
						 'messages' => $messages,
					];
			 }
	  }