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
					Schema::create('password_reset_pins', function (Blueprint $table) {
						  $table->id();
						  $table->string('email')->index();
						  $table->string('pin', 6); // 6-digit PIN
						  $table->timestamp('created_at')->nullable();
						  $table->timestamp('updated_at')->nullable();
						  
					});
			 }
			 
			 /**
			  * Reverse the migrations.
			  */
			 public function down(): void
			 {
					Schema::dropIfExists('password_reset_pins');
			 }
	  };