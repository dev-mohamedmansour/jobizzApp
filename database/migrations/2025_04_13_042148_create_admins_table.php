<?php
	  
	  use Illuminate\Database\Migrations\Migration;
	  use Illuminate\Database\Schema\Blueprint;
	  use Illuminate\Support\Facades\Schema;
	  
	  return new class extends Migration {
			 /**
			  * Run the migrations.
			  */
			 public function up(): void
			 {
					Schema::create('admins', function (Blueprint $table) {
						  $table->id();
						  $table->string('name');
						  $table->string('email')->unique();
						  $table->string('password');
						  $table->string('phone')->unique();
						  $table->string('photo')->default('https://jobizaa.com/still_images/userDefault.jpg');
						  $table->string('pin_code',7)->nullable();
						  $table->timestamp('pin_created_at')->nullable();
						  $table->boolean('confirmed_email')->default(false);
						  $table->timestamp('email_verified_at')->nullable();
						  $table->timestamps();
					});
					
			 }
			 
			 /**
			  * Reverse the migrations.
			  */
			 public function down(): void
			 {
					Schema::dropIfExists('admins');
			 }
	  };
