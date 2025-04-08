<?php
	  
	  namespace App\Providers;
	  
	  use Illuminate\Support\ServiceProvider;
	  use Laravel\Passport\Passport;
	  
	  class PassportServiceProvider extends ServiceProvider
	  {
			 public function register(): void
			 {
					Passport::ignoreRoutes();
			 }
			 
			 public function boot(): void
			 {
					Passport::loadKeysFrom(__DIR__.'/../../storage/');
					Passport::tokensExpireIn(now()->addDays(15));
					Passport::refreshTokensExpireIn(now()->addDays(30));
					Passport::personalAccessTokensExpireIn(now()->addMonths(6));
			 }
	  }