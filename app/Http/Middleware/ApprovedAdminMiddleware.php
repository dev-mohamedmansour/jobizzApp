<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApprovedAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
	  public function handle(Request $request, Closure $next) {
			 if (auth()->guard('admin')->check() && !auth()->guard('admin')->user()->is_approved) {
					return responseJson(401,'Account pending approval');
			 }
			 return $next($request);
	  }
}
