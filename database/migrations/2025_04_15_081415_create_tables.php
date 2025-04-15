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

			// Create job_category pivot
			Schema::create('job_listings_category', function (Blueprint $table) {
				  $table->foreignId('job_listings_id')->constrained();
				  $table->foreignId('category_id')->constrained();
			});

			// Create applications' table
			Schema::create('applications', function (Blueprint $table) {
				  $table->id();
				  $table->foreignId('profile_id')->constrained();
				  $table->foreignId('job_listings_id')->constrained();
				  $table->text('cover_letter');
				  $table->string('resume_path');
				  $table->enum('status', ['pending', 'reviewed', 'accepted', 'rejected'])->default('pending');
				  $table->timestamps();
			});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
        Schema::dropIfExists('job_category');
        Schema::dropIfExists('applications');
    }
};
