<?php
	  
	  namespace App\Models;
	  
	  use Illuminate\Database\Eloquent\Model;
	  
	  class BlockList extends Model
	  {
			 protected $fillable
				  = [
						'email',
						'name',
						'phone',
						'id_of_block_admin'
				  ];
			 protected $hidden = ['id'];
			 protected $casts = [
				  'created_at' => 'datetime:d-m-Y h:i A',
				  'updated_at' => 'datetime:d-m-Y h:i A',
			 ];
	  }
