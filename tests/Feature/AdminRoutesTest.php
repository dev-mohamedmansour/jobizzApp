<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoutesTest extends TestCase
{
use RefreshDatabase; // Resets database for each test
	  public function testAdminRegistration()
	  {
			 $response = $this->postJson('/admin/register', [
				  'email' => 'admin@example.com',
				  'password' => 'password123',
				  // Add other required fields based on your controller
			 ]);
			 $response->assertStatus(201); // Adjust status based on your API
	  }
	  
	  public function testAdminLogin()
	  {
			 $admin = \App\Models\Admin::factory()->create([
				  'email' => 'admin@example.com',
				  'password' => bcrypt('password123'),
			 ]);
			 
			 $response = $this->postJson('/admin/login', [
				  'email' => 'admin@example.com',
				  'password' => 'password123',
			 ]);
			 $response->assertStatus(200)
				  ->assertJsonStructure(['token']); // Assuming token is returned
	  }
	  
	  public function testAdminLogout()
	  {
			 $admin = \App\Models\Admin::factory()->create();
			 $response = $this->actingAs($admin, 'admin')
				  ->postJson('/admin/logout');
			 $response->assertStatus(200);
	  }
	  
	  public function testGetUsers()
	  {
			 $admin = \App\Models\Admin::factory()->create();
			 \App\Models\User::factory()->count(5)->create();
			 
			 $response = $this->actingAs($admin, 'admin')
				  ->getJson('/admin/users');
			 $response->assertStatus(200)
				  ->assertJsonCount(5);
	  }
	  
	  public function testAdminResetPassword()
	  {
			 $admin = \App\Models\Admin::factory()->create();
			 // Simulate token generation (adjust based on your logic)
			 $token = 'some-reset-token'; // Replace with actual token generation
			 
			 $response = $this->actingAs($admin, 'admin')
				  ->withHeaders(['Reset-Token' => $token]) // Adjust header if needed
				  ->postJson('/admin/password/new-password', [
						'password' => 'newpassword123',
				  ]);
			 $response->assertStatus(200);
	  }
	  
	  public function testGetAdminJobs()
	  {
			 $admin = \App\Models\Admin::factory()->create();
			 $company = \App\Models\Company::factory()->create();
			 \App\Models\JobListing::factory()->count(3)->create(['company_id' => $company->id]);
			 
			 $response = $this->actingAs($admin, 'admin')
				  ->getJson('/admin/jobs');
			 $response->assertStatus(200)
				  ->assertJsonCount(3);
	  }
	  
	  public function testUnauthorizedAdminAccess()
	  {
			 $response = $this->getJson('/admin/users');
			 $response->assertStatus(401);
	  }

}