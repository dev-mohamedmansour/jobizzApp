<?php
	  
	  namespace Tests\Feature;
	  
	  use Illuminate\Foundation\Testing\RefreshDatabase;
	  use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
	  use Tests\TestCase;
	  
	  class AuthRoutesTest extends TestCase
	  {
			 use RefreshDatabase;
			 
			 public function testUserRegistration()
			 {
					$response = $this->postJson('/auth/register', [
						 'email' => 'user@example.com',
						 'password' => 'password123',
						 // Add other fields
					]);
					$response->assertStatus(201);
			 }
			 
			 public function testUserLogin()
			 {
					$user = \App\Models\User::factory()->create([
						 'email' => 'user@example.com',
						 'password' => bcrypt('password123'),
					]);
					
					$response = $this->postJson('/auth/login', [
						 'email' => 'user@example.com',
						 'password' => 'password123',
					]);
					$response->assertStatus(200)
						 ->assertJsonStructure(['token']);
			 }
			 
			 public function testAuthHome()
			 {
					$user = \App\Models\User::factory()->create();
					$token = JWTAuth::fromUser($user);
					
					$response = $this->withHeaders(['Authorization' => "Bearer $token"])
						 ->getJson('/auth/home');
					$response->assertStatus(200);
			 }
			 
			 public function testAddProfile()
			 {
					$user = \App\Models\User::factory()->create();
					$token = JWTAuth::fromUser($user);
					
					$response = $this->withHeaders(['Authorization' => "Bearer $token"])
						 ->postJson('/auth/profiles/add-profile', [
							  'name' => 'Test Profile',
							  // Add required fields
						 ]);
					$response->assertStatus(201);
			 }
			 
			 public function testRegistrationWithExistingEmail()
			 {
					$user = \App\Models\User::factory()->create(['email' => 'user@example.com']);
					$response = $this->postJson('/auth/register', [
						 'email' => 'user@example.com',
						 'password' => 'password123',
					]);
					$response->assertStatus(422);
			 }
			 
	  }
