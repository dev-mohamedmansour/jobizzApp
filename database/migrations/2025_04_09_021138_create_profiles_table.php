<?php
	  
	  use Illuminate\Database\Migrations\Migration;
	  use Illuminate\Database\Schema\Blueprint;
	  use Illuminate\Support\Facades\Schema;
	  
	  return new class extends Migration
	  {
			 public function up(): void
			 {
					Schema::create('profiles', function (Blueprint $table) {
						  $table->id();
						  $table->foreignId('user_id')->constrained()->onDelete('cascade');
						  $table->string('title_job');
						  $table->string('job_position');
						  $table->boolean('is_default')->default(false);
						  $table->string('profile_image')->default('https://jobizaa.com/still_images/userDefault.jpg');
						  $table->timestamps();
					});
			 }
			 
			 public function down(): void
			 {
					Schema::dropIfExists('profiles');
			 }
	  };