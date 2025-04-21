<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
	  public function handle($request, Closure $next)
	  {
			 if (!$request->user('admin')->hasVerifiedEmail()) {
					return responseJson(403,
						  'Email verification required'
					);
			 }
			 
			 return $next($request);
	  }
}
