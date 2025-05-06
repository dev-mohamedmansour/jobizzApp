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
						\Illuminate\Http\Middleware\HandleCors::class,
						'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
						 \App\Http\Middleware\EnsureAdminEmailIsVerified::class,
						'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
						 \App\Http\Middleware\ApprovedAdminMiddleware::class,
						'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
				  ]);
			})
			->withExceptions(function (Exceptions $exceptions) {
				  //
			})
			->withProviders([
				 Spatie\Permission\PermissionServiceProvider::class,
			])
			->create();
