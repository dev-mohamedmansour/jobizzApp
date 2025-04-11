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
					Schema::table('documents', function (Blueprint $table) {
						  $table->enum('format', ['pdf', 'images', 'url'])->nullable()
								->after('type');
						  $table->integer('max_images')->default(12)->after('format');
					});
			 }
			 
			 /**
			  * Reverse the migrations.
			  */
			 public function down(): void
			 {
					Schema::table('documents', function (Blueprint $table) {
						  //
					});
			 }
	  };
