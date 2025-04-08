<?php
	  
	  namespace App\Providers;
	  
	  use App\Services\PinService;
	  use Illuminate\Support\ServiceProvider;
	  use Laravel\Passport\Passport;
	  
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
					Passport::loadKeysFrom(__DIR__.'/../secrets/oauth');
					Passport::tokensExpireIn(now()->addDays(15));
					Passport::refreshTokensExpireIn(now()->addDays(30));
					Passport::personalAccessTokensExpireIn(now()->addMonths(6));
					Passport::hashClientSecrets();}
	  }
