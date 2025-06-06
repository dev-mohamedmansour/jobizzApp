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
			Schema::create('job_listings', function (Blueprint $table) {
				  $table->id();
				  $table->foreignId('company_id')->constrained('companies');
				  $table->string('title');
				  $table->string('job_type');
				  $table->decimal('salary', 15, 2);
				  $table->text('description');
				  $table->text('requirement');
				  $table->enum('job_status', ['open', 'closed','cancelled'])->default('open');
				  $table->string('location');
				  $table->string('position');
				  $table->text('benefits')->nullable();
				  $table->string('category_name');
				  $table->timestamps();
			});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
			Schema::dropIfExists('job_listings');
			
	 }
};
