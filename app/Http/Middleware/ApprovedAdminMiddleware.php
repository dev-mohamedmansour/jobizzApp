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
			 $admin = auth()->guard('admin')->user();
			 if (!$admin)
			 {
					return responseJson(401,'Account Not found ');
			 }elseif(!$admin?->is_approved) {
					
					return responseJson(403,
						 'Account pending super-admin approval',
						 $admin ? 'pending' : 'unauthenticated'
					);
			 }
			 return $next($request);
	  }
}
