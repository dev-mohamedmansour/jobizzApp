<?php
	  
	  use App\Http\Middleware\RefreshJwtToken;
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
						RefreshJwtToken::class,
				  ]);
				  $middleware->appendToGroup('api', [
						RefreshJwtToken::class,
				  ]);
				  $middleware->alias([
						'check.reset.token' => \App\Http\Middleware\CheckPasswordResetToken::class,
				  ]);
				  $middleware->appendToGroup('admin', [
						'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
						'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
						'approved.admin' => \App\Http\Middleware\ApprovedAdminMiddleware::class,
				  ]);
				  $middleware->use([
						 // ... other middleware
						 RefreshJwtToken::class,
				  ]);
			})
			->withExceptions(function (Exceptions $exceptions) {
				  //
			})->create();
