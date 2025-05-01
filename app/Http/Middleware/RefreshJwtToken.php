<?php
	  
	  namespace App\Http\Middleware;
	  
	  use Closure;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
	  use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
	  use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
	  use Symfony\Component\HttpFoundation\Response;
	  
	  class RefreshJwtToken
	  {
			 public function handle($request, Closure $next)
			 {
					try {
						  // Check if the token is present
						  $token = JWTAuth::parseToken();
						  
						  // Attempt to refresh the token
						  $newToken = $token->refresh();
						  
						  // Get the response from the next middleware
						  $response = $next($request);
						  
						  // Set the new token in the response header
						  $response->headers->set('Authorization', 'Bearer ' . $newToken);
						  
						  return $response;
						  
					} catch (TokenInvalidException $e) {
						  return response()->json(['error' => 'Token is invalid'], Response::HTTP_UNAUTHORIZED);
						  
					} catch (TokenExpiredException $e) {
						  return response()->json(['error' => 'Token has expired'], Response::HTTP_UNAUTHORIZED);
						  
					} catch (JWTException $e) {
						  return response()->json(['error' => 'Token could not be refreshed'], Response::HTTP_INTERNAL_SERVER_ERROR);
					}
			 }
	  }