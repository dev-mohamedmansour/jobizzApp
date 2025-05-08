<?php
	  
	  namespace App\Jobs;
	  
	  use Illuminate\Bus\Queueable;
	  use Illuminate\Contracts\Queue\ShouldQueue;
	  use Illuminate\Foundation\Bus\Dispatchable;
	  use Illuminate\Queue\InteractsWithQueue;
	  use Illuminate\Queue\SerializesModels;
	  use App\Models\JobListing;
	  use Illuminate\Support\Facades\Log;
	  
	  class DeleteJobAndApplications implements ShouldQueue
	  {
			 use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
			 
			 protected $jobId;
			 
			 /**
			  * Create a new job instance.
			  */
			 public function __construct($jobId)
			 {
					$this->jobId = $jobId;
			 }
			 
			 /**
			  * Execute the job.
			  */
			 public function handle(): void
			 {
					// Find the job
					$job = JobListing::find($this->jobId);
					
					if ($job && $job->job_status === 'cancelled') {
						  // Delete related applications
						  $job->applications()->delete();
						  
						  // Delete the job
						  $job->delete();
						  
						  Log::info("Job ID: {$this->jobId} and its applications have been deleted.");
					} else {
						  Log::warning("Job ID: {$this->jobId} not found or not in cancelled status for deletion.");
					}
			 }
	  }