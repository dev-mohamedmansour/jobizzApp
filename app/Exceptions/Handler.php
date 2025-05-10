<?php
	  
	  namespace App\Exceptions;
	  
	  use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
	  use Illuminate\Auth\AuthenticationException;
	  use Throwable;
	  
	  class Handler extends ExceptionHandler
	  {
			 public function render($request, Throwable $e): \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\Response
			 {
					if ($e instanceof AuthenticationException) {
						  if ($request->is('api/*') || $request->expectsJson()) {
								 return response()->json([
									  'message' => 'Unauthenticated.',
									  'status' => 401,
								 ], 401);
						  }
					}
					
					return parent::render($request, $e);
			 }
	  }