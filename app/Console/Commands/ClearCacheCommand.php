<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearCacheCommand extends Command
{
	  protected $signature = 'cache:clear-table';
	  protected $description = 'Clear the table cache daily';
	  
	  public function handle(): void
	  {
			 // Option 1: Clear the entire cache
			 Cache::flush();
			 
			 // Option 2: Clear specific table-related cache keys (uncomment and adjust as needed)
			 // Cache::forget('table_users');
			 // Cache::forget('table_posts');
			 
			 // Option 3: Clear by tag if using a tag-supporting driver like Redis or Memcached
			 // Cache::tags('tables')->flush();
			 
			 $this->info('Table cache cleared successfully.');
	  }
}
