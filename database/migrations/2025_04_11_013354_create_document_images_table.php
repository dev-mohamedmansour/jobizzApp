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
        Schema::create('document_images', function (Blueprint $table) {
            $table->id();
				 $table->foreignId('document_id')->constrained()->onDelete('cascade');
				 $table->string('path');
				 $table->string('caption')->nullable();
				 $table->string('mime_type');
				 $table->integer('order')->default(0);
				 $table->boolean('is_cover')->default(false);
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
