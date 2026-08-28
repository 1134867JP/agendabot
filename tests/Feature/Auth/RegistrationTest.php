<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_uses_complete_onboarding(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect(route('onboarding.step1'));
    }

    public function test_legacy_registration_post_redirects_to_complete_onboarding(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('onboarding.step1'));
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
