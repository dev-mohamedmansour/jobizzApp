<?php
	  
	  use Illuminate\Foundation\Application;
	  use Illuminate\Foundation\Configuration\Exceptions;
	  use Illuminate\Foundation\Configuration\Middleware;
	  
	  return Application::configure(basePath: dirname(__DIR__))
			->withRouting(
				 web: __DIR__ . '/../routes/web.php',
				 api: __DIR__ . '/../routes/api.php',
				 commands: __DIR__ . '/../routes/console.php',
				 health: '/up',
			)
			->withMiddleware(function (Middleware $middleware) {
				  // Add JWT refresh middleware
				  $middleware->appendToGroup('web', [
						\App\Http\Middleware\RefreshJwtToken::class,
				  ]);
				  $middleware->appendToGroup('api', [
						\App\Http\Middleware\RefreshJwtToken::class,
				  ]);
				  
				  // Or if you want it for all requests
				  $middleware->use([
						 // ... other middleware
						 \App\Http\Middleware\RefreshJwtToken::class,
				  ]);
			})
			->withExceptions(function (Exceptions $exceptions) {
				  //
			})->create();
