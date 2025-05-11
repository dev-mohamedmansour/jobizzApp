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
			Schema::table('admins', function (Blueprint $table) {
				  $table->string('verification_token')->nullable()->after('pin_code');
				  $table->timestamp('token_created_at')->nullable()->after('pin_created_at');
			});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
			Schema::table('admins', function (Blueprint $table) {
				  $table->dropColumn(['verification_token', 'token_created_at']);
			});
    }
};
