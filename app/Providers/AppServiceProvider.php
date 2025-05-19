<?php
	  
	  namespace App\Providers;
	  
	  use App\Services\PinService;
	  use Illuminate\Cache\RateLimiting\Limit;
	  use Illuminate\Http\Request;
	  use Illuminate\Support\Facades\RateLimiter;
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
					RateLimiter::for('limiter', function (Request $request) {
						  return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip())->response(function () {
								 return responseJson(429,
									   'Too many requests. Try again later.',
								 'Try again after 1 minute'
								 );
						  });
					});
			 }
	  }
