<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
	  // database/migrations/YYYY_MM_DD_create_document_images_table.php
	  public function up(): void
	  {
			 Schema::create('document_images', function (Blueprint $table) {
					$table->id();
					$table->foreignId('document_id')->constrained()->onDelete('cascade');
					$table->string('path');
					$table->timestamps();
			 });
	  }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_images');
    }
};
