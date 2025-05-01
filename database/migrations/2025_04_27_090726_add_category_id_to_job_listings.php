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
				 $table->string('category_name')->change();
				 // Add foreign key constraint
				 $table->foreign('category_name')
					  ->references('name')
					  ->on('categories')
					  ->onUpdate('cascade')
					  ->onDelete('cascade');
		  });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
				 $table->dropForeign(['category_name']);
		  });
    }
};
