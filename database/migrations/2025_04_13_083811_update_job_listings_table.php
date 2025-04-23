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
			Schema::table('job_listings', function (Blueprint $table) {
				  $table->foreignId('company_id')->constrained('companies');
				  $table->string('title');
				  $table->string('job_type');
				  $table->decimal('salary', 15, 2);
				  $table->text('description');
				  $table->text('requirement');
				  $table->enum('job_status', ['open', 'closed'])->default('open');
				  $table->string('location');
				  $table->json('benefits')->nullable();
				  $table->timestamps();
			});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
