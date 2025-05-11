<?php
	  
	  namespace App\Http\Middleware;
	  
	  use Closure;
	  use Illuminate\Http\Request;
	  use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
	  
	  class JwtAuthentication
	  {
			 public function handle(Request $request, Closure $next)
			 {
					try {
						  // Force Accept header to application/json
						  $request->headers->set('Accept', 'application/json');
						  
						  // Attempt to parse and authenticate the token
						  $user = JWTAuth::parseToken()->authenticate();
						  
						  if (!$user) {
								 return response()->json([
									  'status' => 'error1',
									  'message' => 'Unauthenticated',
								 ], 401);
						  }
					} catch (TokenExpiredException $e) {
						  return response()->json([
								'status' => 'error2',
								'message' => 'Token has expired',
						  ], 401);
					} catch (TokenInvalidException $e) {
						  return response()->json([
								'status' => 'error3',
								'message' => 'Invalid token',
						  ], 401);
					} catch (JWTException $e) {
						  return response()->json([
								'status' => 'error4',
								'message' => 'Unauthenticated',
								$e->getMessage()
						  ], 401);
					}
					
					// Proceed to the next middleware and ensure JSON response
					$response = $next($request);
					return $response->header('Content-Type', 'application/json');
			 }
	  }