<?php
	  
	  return [
			 
			 /*
			 |--------------------------------------------------------------------------
			 | Cross-Origin Resource Sharing (CORS) Configuration
			 |--------------------------------------------------------------------------
			 |
			 | Here you may configure your settings for cross-origin resource sharing
			 | or "CORS".
			 |This determines what cross-origin operations may execute
			 | in web browsers.
			 You are free to adjust these settings as needed.
			 |
			 | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
			 |
			 */
			 

	 'paths' => ['api/*', 'login', 'logout', 'refresh'], // Include JWT routes
	 'allowed_methods' => ['*'],
	 'allowed_origins' => ['*'], // Set specific domains in production
	 'allowed_origins_patterns' => [],
	 'allowed_headers' => ['*'], // Important for JWT: allows Authorization header
	 'exposed_headers' => ['Authorization'], // Expose Authorization header to client
	 'max_age' => 0,
	 'supports_credentials' => false,
	  
	  ];
