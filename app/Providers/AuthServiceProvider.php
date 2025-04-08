<?php
	  
	  namespace App\Providers;
	  
	  use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
	  use Laravel\Passport\Passport;
	  
	  class AuthServiceProvider extends ServiceProvider
	  {
			 public function boot(): void
			 {
					$this->registerPolicies();
					
					Passport::tokensExpireIn(now()->addDays(15));
					Passport::refreshTokensExpireIn(now()->addDays(30));
					Passport::personalAccessTokensExpireIn(now()->addMonths(6));
					
					Passport::routes();
			 }
	  }