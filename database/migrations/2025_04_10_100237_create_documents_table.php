<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
	  // database/migrations/YYYY_MM_DD_create_documents_table.php
	  public function up(): void
	  {
			 Schema::create('documents', function (Blueprint $table) {
					$table->id();
					$table->foreignId('profile_id')->constrained()->onDelete('cascade');
					$table->string('name')->nullable()->default('Document');
					$table->unsignedInteger('image_count')->default(0);
					$table->enum('type', ['cv', 'portfolio']);
					$table->enum('format', ['images', 'pdf', 'url','cv'])->default('images');
					$table->string('path')->nullable(); // For CV files
					$table->string('url')->nullable(); // For portfolio URLs
					$table->timestamps();
					
					// Indexes for faster querying
					$table->index(['type', 'profile_id']);
			 });
	  }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
