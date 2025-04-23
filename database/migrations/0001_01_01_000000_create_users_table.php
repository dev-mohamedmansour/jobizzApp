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
					Schema::create('users', function (Blueprint $table) {
						  $table->id();
						  $table->string('name');
						  $table->string('email')->unique();
						  // Password should be nullable for social login users
						  $table->string('password')->nullable();
						  // Social login fields
						  $table->string('provider_id')->nullable(
						  ); // Changed to string as some providers use non-numeric IDs
						  $table->string('provider_name')->nullable();
						  $table->rememberToken();
						  // Fixed typo in field name and set default value
						  $table->boolean('confirmed_email')->default(false);
						  $table->char('pin_code', 7)->nullable();
						  $table->dateTime('pin_created_at')->nullable(); // When the PIN was sent
						  $table->timestamp('email_verified_at')->nullable();
						  $table->timestamps();
					});
					
					Schema::create(
						 'password_reset_tokens', function (Blueprint $table) {
						  $table->string('email')->primary();
						  $table->string('token');
						  $table->timestamp('created_at')->nullable();
					}
					);
					
					Schema::create('sessions', function (Blueprint $table) {
						  $table->string('id')->primary();
						  $table->foreignId('user_id')->nullable()->index();
						  $table->string('ip_address', 45)->nullable();
						  $table->text('user_agent')->nullable();
						  $table->longText('payload');
						  $table->integer('last_activity')->index();
					});
			 }
			 
			 /**
			  * Reverse the migrations.
			  */
			 public function down(): void
			 {
					Schema::dropIfExists('users');
					Schema::dropIfExists('password_reset_tokens');
					Schema::dropIfExists('sessions');
			 }
	  };
