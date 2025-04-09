<?php
	  
	  use Illuminate\Database\Migrations\Migration;
	  use Illuminate\Database\Schema\Blueprint;
	  use Illuminate\Support\Facades\Schema;
	  
	  return new class extends Migration
	  {
			 public function up(): void
			 {
					Schema::create('educations', function (Blueprint $table) {
						  $table->id();
						  $table->foreignId('profile_id')->constrained()->onDelete('cascade');
						  $table->string('institution');
						  $table->string('degree');
						  $table->string('field_of_study');
						  $table->date('start_date');
						  $table->date('end_date')->nullable();
						  $table->boolean('is_current')->default(false);
						  $table->text('description')->nullable();
						  $table->timestamps();
					});
			 }
			 
			 public function down(): void
			 {
					Schema::dropIfExists('educations');
			 }
	  };