<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_unavailable(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_public_registration_post_is_unavailable(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
        $this->assertSame(0, User::query()->count());
    }
}
