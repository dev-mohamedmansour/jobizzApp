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
				  $table->text('description');
				  $table->string('location');
				  $table->string('salary_range');
				  $table->string('employment_type');
				  $table->timestamp('expiry_date');
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
