<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPasswordResetToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
			try {
				  $payload = auth()->payload();
				  
				  if ($payload->get('purpose') !== 'password_reset') {
						 return response()->json([
							  'status' => 401,
							  'message' => 'Invalid token purpose'
						 ], 401);
				  }
				  
			} catch (\Exception $e) {
				  return response()->json([
						'status' => 401,
						'message' => 'Invalid authorization token'
				  ], 401);
			}
			
			return $next($request);
    }
}
