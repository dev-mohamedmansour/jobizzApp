<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
			Schema::create('block_lists', function (Blueprint $table) {
				  $table->id();
				  $table->string('name');
				  $table->string('id_of_block_admin');
				  $table->string('email')->unique();
				  $table->string('phone');
				  $table->timestamps();
			});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
			Schema::dropIfExists('block_lists');
    }
};
