<?php
	  
	  namespace App\Http\Middleware;
	  
	  use Closure;
	  use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
	  use PHPOpenSourceSaver\JWTAuth\Http\Middleware\BaseMiddleware;
	  
	  class RefreshJwtToken extends BaseMiddleware
	  {
			 public function handle($request, Closure $next)
			 {
					// Process the request first
					$response = $next($request);
					
					try {
						  // Check if token needs refresh (within last 25% of TTL)
						  $token = JWTAuth::parseToken();
						  $payload = $token->getPayload();
						  
						  $ttl = JWTAuth::factory()->getTTL() * 60; // Get TTL in seconds
						  $iat = $payload->get('iat');
						  $shouldRefresh = time() - $iat > ($ttl * 0.75);
						  
						  if ($shouldRefresh && $newToken = JWTAuth::refresh()) {
								 $response->headers->set('Authorization', 'Bearer '.$newToken);
								 $response->headers->set('X-New-Token', $newToken);
								 $response->headers->set('Access-Control-Expose-Headers', 'Authorization, X-New-Token');
						  }
					} catch (\Exception $e) {
						  // Token couldn't be refreshed, continue without
					}
					
					return $response;
			 }
	  }