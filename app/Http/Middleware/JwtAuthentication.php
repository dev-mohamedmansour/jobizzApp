<?php
	  
	  namespace App\Http\Middleware;
	  
	  use Closure;
	  use Illuminate\Http\Request;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
	  use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
	  
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
								 return responseJson(
									  401, 'Unauthorized', 'Token is invalid or expired'
								 );
						  }
					} catch (TokenExpiredException $e) {
						  return responseJson(401,
								'Token has expired',$e->getMessage());
					} catch (TokenInvalidException $e) {
						  return responseJson(401,'Invalid token', $e->getMessage());
					} catch (JWTException $e) {
						  return responseJson(401,'Unauthenticated',
								$e->getMessage());
					}
					// Proceed to the next middleware and ensure JSON response
					$response = $next($request);
					return $response->header('Content-Type', 'application/json');
			 }
	  }