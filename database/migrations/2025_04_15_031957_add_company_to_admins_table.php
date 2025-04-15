<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
			Schema::table('admins', function (Blueprint $table) {
				  $table->foreignId('company_id')->nullable()->constrained(); // Keep this
				  $table->boolean('is_approved')->default(false);
				  $table->foreignId('approved_by')->nullable()->constrained('admins');
			});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            //
        });
    }
};
