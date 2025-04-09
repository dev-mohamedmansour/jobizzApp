<?php
	  
	  use Illuminate\Database\Migrations\Migration;
	  use Illuminate\Database\Schema\Blueprint;
	  use Illuminate\Support\Facades\Schema;
	  
	  return new class extends Migration
	  {
			 public function up(): void
			 {
					Schema::create('profile_documents', function (Blueprint $table) {
						  $table->id();
						  $table->foreignId('profile_id')->constrained()->onDelete('cascade');
						  $table->string('name');
						  $table->string('path');
						  $table->enum('type', ['cv', 'portfolio', 'certificate', 'other']);
						  $table->string('url')->nullable();
						  $table->timestamps();
					});
			 }
			 
			 public function down(): void
			 {
					Schema::dropIfExists('profile_documents');
			 }
	  };