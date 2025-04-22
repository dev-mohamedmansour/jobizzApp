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
			Schema::create('companies', function (Blueprint $table) {
				  $table->id();
				  $table->string('name')->unique();
				  $table->foreignId('admin_id')->constrained();
				  $table->string('logo')->default('https://jobizaa.com/still_images/companyLogoDefault.jpeg');
				  $table->text('description')->nullable();
				  $table->string('location')->nullable();
				  $table->string('website')->nullable();
				  $table->string('size')->nullable();
				  $table->integer('hired_people')->nullable();
				  $table->timestamps();
			});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
