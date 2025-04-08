<?php
	  
	  namespace App\Providers;
	  
	  use App\Services\PinService;
	  use Illuminate\Support\ServiceProvider;
	  
	  class AppServiceProvider extends ServiceProvider
	  {
			 /**
			  * Register any application services.
			  */
			 public function register(): void
			 {
					$this->app->singleton(PinService::class, function ($app) {
						  return new PinService();
						  
					});
			 }
			 
			 /**
			  * Bootstrap any application services.
			  */
			 public function boot(): void
			 {
			 }
	  }
