<?php
	  
	  use Illuminate\Database\Migrations\Migration;
	  use Illuminate\Database\Schema\Blueprint;
	  use Illuminate\Support\Facades\Schema;
	  
	  return new class extends Migration
	  {
			 /**
			  * Run the migrations.
			  */
			 public function up(): void
			 {
					// Create categories' table
					Schema::create('categories', function (Blueprint $table) {
						  $table->id();
						  $table->string('name')->unique();
						  $table->string('slug')->unique();
						  $table->timestamps();
					});
					// Create applications' table
					Schema::create('applications', function (Blueprint $table) {
						  $table->id();
						  $table->foreignId('profile_id')->constrained('profiles')->onUpdate('cascade')->onDelete('cascade');
						  $table->foreignId('job_id')->constrained('job_listings')->onUpdate('cascade')->onDelete('cascade');
						  $table->string('cover_letter')->default('No cover letter provided');
						  $table->string('resume_path');
						  $table->enum('status', ['pending', 'reviewed', 'accepted', 'rejected','submitted','team-matching','final-hr-interview','technical-interview','screening-interview'])->default('pending');
						  $table->timestamps();
					});
			 }
			 
			 public function down(): void
			 {
					Schema::dropIfExists('categories');
					Schema::dropIfExists('applications');
			 }
	  };
